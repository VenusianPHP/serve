<?php

namespace Mariner\Core\Console;

use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;
use Voyager\Console\Command;
use Voyager\Encryption\Encrypter;
use Voyager\Filesystem\Filesystem;

#[AsCommand(name: 'install:web')]
class WebInstallCommand extends Command
{
    protected ?string $signature = 'install:web
                    {--force : Overwrite routes/web.php, routes/api.php, public/index.php and resources/views/welcome.blade.php if they exist}';

    protected string $description = 'Publish the routes, front controller and welcome page, create the session and view storage, and set an app key';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * Publish each file that is missing (or every one, with --force), creating its directory.
     *
     * @return int
     */
    public function handle(): int
    {
        $failed = false;

        foreach ($this->files() as $stub => $to) {
            $failed = ! $this->publish(__DIR__."/stubs/{$stub}.stub", $to) || $failed;
        }

        foreach ($this->directories() as $directory) {
            $failed = ! $this->makeDirectory($directory) || $failed;
        }

        $failed = ! $this->ensureAppKey() || $failed;

        return $failed ? self::FAILURE : self::SUCCESS;
    }

    /**
     * The storage directories the web middleware writes to: file sessions and compiled views.
     *
     * @return array<int, string>
     */
    protected function directories(): array
    {
        return [
            storage_path('framework/sessions'),
            storage_path('framework/views'),
        ];
    }

    /**
     * Create $directory with a .gitignore that keeps it in git but not its contents.
     *
     * @param  string  $directory
     * @return bool  false if it could not be created
     */
    protected function makeDirectory(string $directory): bool
    {
        $relative = str_replace(base_path().'/', '', $directory);

        if ($this->files->isDirectory($directory)) {
            $this->components->twoColumnDetail("Directory [{$relative}] already exists", '<fg=yellow;options=bold>SKIPPED</>');

            return true;
        }

        try {
            $this->files->ensureDirectoryExists($directory);
            $this->files->put($directory.'/.gitignore', "*\n!.gitignore\n");
        } catch (Throwable $e) {
            $this->components->error("Could not create [{$relative}]. ".$e->getMessage());

            return false;
        }

        $this->components->task("Creating directory [{$relative}]");

        return true;
    }

    /**
     * Give the app an encryption key when .env has none: cookies, sessions and CSRF all need one.
     * Creates .env from .env.example first if it is missing. An existing key is never replaced.
     *
     * @return bool  false if .env could not be written
     */
    protected function ensureAppKey(): bool
    {
        $env = base_path('.env');

        if (! $this->files->exists($env) && $this->files->exists(base_path('.env.example'))) {
            $this->files->copy(base_path('.env.example'), $env);
        }

        $contents = $this->files->exists($env) ? $this->files->get($env) : '';

        if (preg_match('/^APP_KEY=(.+)$/m', $contents, $match) && trim($match[1]) !== '') {
            $this->components->twoColumnDetail('Application key already set', '<fg=yellow;options=bold>SKIPPED</>');

            return true;
        }

        $key = 'base64:'.base64_encode(Encrypter::generateKey((string) config('app.cipher', 'AES-256-CBC')));

        $contents = preg_match('/^APP_KEY=.*$/m', $contents)
            ? preg_replace('/^APP_KEY=.*$/m', 'APP_KEY='.$key, $contents)
            : rtrim($contents, "\n").($contents === '' ? '' : "\n")."APP_KEY={$key}\n";

        try {
            $this->files->put($env, $contents);
        } catch (Throwable $e) {
            $this->components->error('Could not write the application key to [.env]. '.$e->getMessage());

            return false;
        }

        // The running process booted without a key; later commands in it should see the new one.
        config(['app.key' => $key]);

        $this->components->task('Setting the application key in [.env]');

        return true;
    }

    /**
     * Stub name => where it is published.
     *
     * @return array<string, string>
     */
    protected function files(): array
    {
        return [
            'web' => base_path('routes/web.php'),
            'api' => base_path('routes/api.php'),
            'index' => public_path('index.php'),
            'welcome' => resource_path('views/welcome.blade.php'),
        ];
    }

    /**
     * Copy the stub to $to unless $to exists and --force was not given.
     *
     * @param  string  $from
     * @param  string  $to
     * @return bool  false if the file could not be written
     */
    protected function publish(string $from, string $to): bool
    {
        $relative = str_replace(base_path().'/', '', $to);

        if ($this->files->exists($to) && ! $this->option('force')) {
            $this->components->twoColumnDetail("File [{$relative}] already exists", '<fg=yellow;options=bold>SKIPPED</>');

            return true;
        }

        try {
            $this->files->ensureDirectoryExists(dirname($to));
            $written = $this->files->copy($from, $to);
        } catch (Throwable $e) {
            $written = false;
        }

        if (! $written) {
            $this->components->error("Could not write [{$relative}].".(isset($e) ? ' '.$e->getMessage() : ''));

            return false;
        }

        $this->components->task("Copying file [{$relative}]");

        return true;
    }
}
