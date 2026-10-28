<?php

namespace Mariner\Core\Console;

use Mariner\Core\Server\HttpServer;
use Mariner\Core\Server\ServedRequest;
use Mariner\Core\Server\ServerOptions;
use Mariner\Core\Server\Supervisor;
use Mariner\Core\Server\Workers\ProcessWorker;
use Mariner\Core\Server\Workers\ThreadWorker;
use Voyager\IOPools\WorkerPools\Thread\ThreadSupport;
use Voyager\IOPools\WorkerPools\WorkerPoolManager;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;
use Voyager\Console\Command;
use Voyager\Contracts\IOPools\Loop;

/**
 * Serve the app over HTTP on its event loop: build the server, attach it, run the loop. What is
 * process-wide lives here, not in the server: each request gets a console line when the output is
 * a terminal (or with -v), which --quiet silences. The first Ctrl-C or SIGTERM drains the server
 * (the requests in flight are answered, within serve.drain_timeout) and then the loop stops; a
 * second one ends the process at once.
 */
#[AsCommand(name: 'serve')]
class ServeCommand extends Command
{
    protected ?string $signature = 'serve
                    {--host= : The address to listen on (config serve.host)}
                    {--port= : The port to listen on (config serve.port)}
                    {--max-in-flight= : Requests handled at once; above 1 needs Linux (config serve.max_in_flight)}
                    {--workers= : Workers sharing the port; above 1 needs Linux (config serve.workers)}
                    {--driver=auto : How workers run: process, thread, or auto (threads where this PHP runs them, else processes). A PHP fatal error in a thread worker ends the whole server; process workers contain it}
                    {--https : Serve as behind a proxy that terminates TLS}';

    protected string $description = 'Serve the application over HTTP on its event loop';

    public function handle(): int
    {
        try {
            $options = $this->serverOptions();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $workers = (int) ($this->option('workers') ?? $this->venusian['config']->get('serve.workers', 1));

        if ($workers > 1) {
            return $this->supervise($workers, $options);
        }

        /** @var Loop $loop */
        $loop = $this->venusian->make('event-loop');

        try {
            $server = (new HttpServer($this->venusian, $options))->listen();
        } catch (Throwable $e) {
            $this->components->error($e->getMessage());

            return self::FAILURE;
        }

        $server->attach($loop);

        if ($this->output->isDecorated() || $this->output->isVerbose()) {
            $server->onRequest($this->requestLine(...));
        }

        // The first Ctrl-C or SIGTERM answers what is in flight, then stops; a second stops now.
        $timeout = (float) $this->venusian['config']->get('serve.drain_timeout', 30.0);
        $loop->onStopSignal(function () use ($server, $loop, $timeout) {
            $this->components->info('Stopping: answering the requests in flight. Press Ctrl+C again to stop now.');
            $server->drain($timeout)->then(fn () => $loop->stop(0));

            return true;
        });

        $this->components->info(sprintf(
            'Serving on %s://%s:%d, up to %d connections at once. Press Ctrl+C to stop.',
            $options->https ? 'https' : 'http', $options->host, $server->port(), $server->maxConnections(),
        ));

        return $loop->run();
    }

    /**
     * Run $workers serve:worker processes on one port, balanced by the kernel, restarted when they
     * die and stopped together.
     */
    protected function supervise(int $workers, ServerOptions $options): int
    {
        if (PHP_OS_FAMILY !== 'Linux') {
            $this->components->error('More than one worker needs Linux, where SO_REUSEPORT balances them: run one worker per port behind a proxy on '.PHP_OS_FAMILY.'.');

            return self::FAILURE;
        }

        $driver = $this->driver();

        if (is_null($driver)) {
            return self::FAILURE;
        }

        $this->components->info(sprintf(
            'Serving on %s://%s:%d with %d %s workers, each up to %d connections at once. Press Ctrl+C to stop.',
            $options->https ? 'https' : 'http', $options->host, $options->port, $workers, $driver, $options->maxConnections(),
        ));

        if ($driver === 'thread') {
            $this->components->warn('Thread workers share one process: a PHP fatal error in any of them ends the whole server.');
        }

        $drain = (float) $this->venusian['config']->get('serve.drain_timeout', 30.0);

        $supervisor = new Supervisor(
            $driver === 'thread' ? $this->threadWorkers($options, $drain) : $this->processWorkers($options),
            $workers,
            fn (string $message) => $this->components->warn($message),
            stop_timeout: $drain + 5.0,
        );

        return $supervisor->run();
    }

    /**
     * The worker driver to use, or null after saying why none can be: auto picks threads where this
     * PHP runs them (ThreadSupport::probe()) and processes otherwise, and says which and why.
     */
    protected function driver(): ?string
    {
        $asked = (string) $this->option('driver');

        if (! in_array($asked, ['auto', 'process', 'thread'], true)) {
            $this->components->error("There is no \"{$asked}\" driver: use process, thread or auto.");

            return null;
        }

        if ($asked === 'process') {
            return 'process';
        }

        $probe = ThreadSupport::probe();

        if ($asked === 'thread' && ! $probe->supported) {
            $this->components->error("Thread workers can't run in this PHP: {$probe->reason}.");

            return null;
        }

        if ($asked === 'auto') {
            $this->components->info($probe->supported
                ? "Driver auto: threads, since {$probe->reason}."
                : "Driver auto: processes, since threads don't run here ({$probe->reason}).");
        }

        return $probe->supported ? 'thread' : 'process';
    }

    /** @return \Closure(int): ProcessWorker */
    protected function processWorkers(ServerOptions $options): \Closure
    {
        $console = $this->console();

        return fn (int $index) => new ProcessWorker([...$console, 'serve:worker', "--index={$index}",
            "--host={$options->host}", "--port={$options->port}", "--max-in-flight={$options->max_in_flight}",
            ...($options->https ? ['--https'] : []), ...($this->output->isQuiet() ? ['--quiet'] : []),
            ...($this->output->isDecorated() ? ['--ansi'] : []), ...($this->output->isVerbose() ? ['-v'] : []),
        ]);
    }

    /** @return \Closure(int): ThreadWorker */
    protected function threadWorkers(ServerOptions $options, float $drain): \Closure
    {
        // Every option, as plain values a thread can be handed, with the port shared between workers.
        $plain = [
            ...get_object_vars($options),
            'limits' => get_object_vars($options->limits),
            'reuse_port' => true,
            'lines' => ! $this->output->isQuiet() && ($this->output->isDecorated() || $this->output->isVerbose()),
        ];
        $bootstrap = $this->venusian->bootstrapPath('app.php');
        $autoload = WorkerPoolManager::autoloader();

        return fn (int $index) => new ThreadWorker($autoload, $bootstrap, $index, $plain, $drain);
    }

    /**
     * How to run this app's console again: PHP and the script that started this one, or the binary
     * alone when the app is packaged into one (it is its own PHP).
     *
     * @return list<string>
     */
    protected function console(): array
    {
        $script = realpath($_SERVER['argv'][0] ?? '') ?: ($_SERVER['argv'][0] ?? '');

        return $script === realpath(PHP_BINARY) ? [PHP_BINARY] : [PHP_BINARY, $script];
    }

    /** The server's options: config/serve.php, overridden by the command line. */
    protected function serverOptions(): ServerOptions
    {
        $config = (array) $this->venusian['config']->get('serve', []);

        foreach (['host' => 'host', 'port' => 'port', 'max-in-flight' => 'max_in_flight'] as $option => $key) {
            if (! is_null($value = $this->option($option))) {
                $config[$key] = $value;
            }
        }

        if ($this->option('https')) {
            $config['https'] = true;
        }

        return ServerOptions::fromConfig($config);
    }

    /** One console line per request: time, status, method, path, duration. */
    protected function requestLine(ServedRequest $served): void
    {
        $color = match (true) {
            $served->status >= 500 || $served->status === 0 => 'red',
            $served->status >= 400 => 'yellow',
            default => 'green',
        };

        $this->output->writeln(sprintf(
            '  <fg=gray>%s</> <fg=%s>%3d</> %s %s <fg=gray>%.2f ms</>',
            date('H:i:s'), $color, $served->status, $served->method, $served->path, $served->seconds * 1000,
        ));
    }
}
