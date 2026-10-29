<?php

use Mariner\Core\Http\Kernel;
use Mariner\Http\Request;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Contracts\Console\Kernel as ConsoleKernel;
use Voyager\Filesystem\Filesystem;

beforeEach(function () {
    $this->files = new Filesystem;
    $this->root = WebApp::copy();
    $this->files->deleteDirectory($this->root.'/routes');
    $this->stubs = dirname(__DIR__, 2).'/src/Mariner/Core/Console/stubs';
});

afterEach(function () {
    if (isset($this->app)) {
        WebApp::tearDown($this);
    }

    @chmod($this->root.'/public', 0755);
    $this->files->deleteDirectory($this->root);
});

/** Run install:web in the temporary app: [exit code, output]. */
function installWeb($test, array $options = []): array
{
    if (isset($test->app)) {
        WebApp::tearDown($test);
    }

    $test->app = WebApp::bootFrom($test->root);
    $output = new BufferedOutput;
    $status = $test->app->make(ConsoleKernel::class)->handle(new ArrayInput(['command' => 'install:web', ...$options]), $output);

    return [$status, $output->fetch()];
}

it('creates the route files and the front controller in a new app', function () {
    [$status, $output] = installWeb($this);

    expect($status)->toBe(0)
        ->and(file_get_contents($this->root.'/routes/web.php'))->toBe(file_get_contents($this->stubs.'/web.stub'))
        ->and(file_get_contents($this->root.'/routes/api.php'))->toBe(file_get_contents($this->stubs.'/api.stub'))
        ->and(file_get_contents($this->root.'/public/index.php'))->toBe(file_get_contents($this->stubs.'/index.stub'))
        ->and(file_get_contents($this->root.'/resources/views/welcome.blade.php'))->toBe(file_get_contents($this->stubs.'/welcome.stub'))
        ->and($output)->toContain('routes/web.php')->toContain('public/index.php')->toContain('resources/views/welcome.blade.php');
});

it('changes nothing when run again', function () {
    installWeb($this);
    clearstatcache();
    $before = array_map(fn ($file) => [filemtime($file), md5_file($file)], glob($this->root.'/{routes,public,resources/views}/*.php', GLOB_BRACE));

    [$status, $output] = installWeb($this);
    clearstatcache();
    $after = array_map(fn ($file) => [filemtime($file), md5_file($file)], glob($this->root.'/{routes,public,resources/views}/*.php', GLOB_BRACE));

    expect($status)->toBe(0)
        ->and($after)->toBe($before)
        ->and(substr_count($output, 'SKIPPED'))->toBe(8);
});

it('keeps an edited file unless forced', function () {
    installWeb($this);
    file_put_contents($this->root.'/routes/web.php', '<?php // mine');

    installWeb($this);
    $kept = file_get_contents($this->root.'/routes/web.php');
    installWeb($this, ['--force' => true]);

    expect($kept)->toBe('<?php // mine')
        ->and(file_get_contents($this->root.'/routes/web.php'))->toBe(file_get_contents($this->stubs.'/web.stub'));
});

it('writes only what is missing', function () {
    mkdir($this->root.'/routes');
    file_put_contents($this->root.'/routes/web.php', '<?php // mine');

    [, $output] = installWeb($this);

    expect(file_get_contents($this->root.'/routes/web.php'))->toBe('<?php // mine')
        ->and(file_exists($this->root.'/routes/api.php'))->toBeTrue()
        ->and($output)->toContain('File [routes/web.php] already exists');
});

it('fails, naming the file, when it cannot write one', function () {
    mkdir($this->root.'/public', 0555);

    [$status, $output] = installWeb($this);

    expect($status)->toBe(1)
        ->and($output)->toContain('Could not write [public/index.php]')
        ->and(file_exists($this->root.'/routes/web.php'))->toBeTrue();
})->skip(fn () => function_exists('posix_getuid') && posix_getuid() === 0, 'root writes anywhere');

it('serves the installed routes: web at the root, api under /api with its group', function () {
    installWeb($this);
    unlink($this->root.'/config/routing.php');
    file_put_contents($this->root.'/routes/api.php', "<?php\n\nuse Mariner\\NutsAndBolts\\Facades\\Route;\n\nRoute::get('/ping', fn () => 'pong')->name('api.ping');\n");

    WebApp::tearDown($this);
    $this->app = WebApp::bootFrom($this->root);
    config(['session.driver' => 'array']);
    $kernel = $this->app->make(Kernel::class);

    $home = $kernel->handle(Request::create('/'));
    $ping = $kernel->handle(Request::create('/api/ping'));
    $up = $kernel->handle(Request::create('/up'));
    $route = $this->app['router']->getRoutes()->getByName('api.ping');

    expect($home->getStatusCode())->toBe(200)
        ->and($home->getContent())->toContain('<h1>Venusian</h1>')->toContain(PHP_VERSION.' ('.PHP_SAPI.')')->toContain('venusian/serve')->toContain('https://venusian.projectsaturnstudios.com/docs/serve/serving')->not->toContain('{{')
        ->and([$ping->getStatusCode(), $ping->getContent()])->toBe([200, 'pong'])
        ->and([$up->getStatusCode(), $up->getContent()])->toBe([200, 'OK'])
        ->and($route->gatherMiddleware())->toContain('api');
});

it('creates the session and compiled-view directories', function () {
    $this->files->deleteDirectory($this->root.'/storage/framework/sessions');
    $this->files->deleteDirectory($this->root.'/storage/framework/views');

    [$status, $output] = installWeb($this);

    expect($status)->toBe(0)
        ->and(is_dir($this->root.'/storage/framework/sessions'))->toBeTrue()
        ->and(is_dir($this->root.'/storage/framework/views'))->toBeTrue()
        ->and(file_get_contents($this->root.'/storage/framework/sessions/.gitignore'))->toBe("*\n!.gitignore\n")
        ->and($output)->toContain('storage/framework/sessions');
});

it('sets an app key in .env when it is empty, creating .env from .env.example', function () {
    expect(file_exists($this->root.'/.env'))->toBeFalse();
    file_put_contents($this->root.'/.env.example', "APP_NAME=Venusian\nAPP_KEY=\nLOG_LEVEL=debug\n");

    [$status, $output] = installWeb($this);
    $env = file_get_contents($this->root.'/.env');
    preg_match('/^APP_KEY=base64:(.+)$/m', $env, $match);

    expect($status)->toBe(0)
        ->and(strlen(base64_decode($match[1] ?? '', true) ?: ''))->toBe(32)
        ->and($env)->toContain("APP_NAME=Venusian\n")->toContain("LOG_LEVEL=debug\n")
        ->and(substr_count($env, 'APP_KEY='))->toBe(1)
        ->and(file_get_contents($this->root.'/.env.example'))->toContain("APP_KEY=\n")
        ->and($output)->toContain('Setting the application key');
});

it('adds an app key to a .env that has no APP_KEY line', function () {
    file_put_contents($this->root.'/.env', "APP_NAME=Venusian\n");

    installWeb($this);

    expect(file_get_contents($this->root.'/.env'))->toMatch('/^APP_NAME=Venusian\nAPP_KEY=base64:[A-Za-z0-9+\/=]{44}\n$/');
});

it('never replaces an existing app key, even with --force', function () {
    file_put_contents($this->root.'/.env', "APP_KEY=base64:keepme\n");

    [, $output] = installWeb($this, ['--force' => true]);

    expect(file_get_contents($this->root.'/.env'))->toBe("APP_KEY=base64:keepme\n")
        ->and($output)->toContain('Application key already set');
});

it('publishes the users migration, which never --force replaces, and migrates', function () {
    $migration = $this->root.'/database/migrations/0001_01_01_000000_create_users_table.php';

    [$status, $output] = installWeb($this);
    $published = file_get_contents($migration);
    file_put_contents($migration, $published."// mine\n");
    installWeb($this, ['--force' => true]);
    $kept = file_get_contents($migration);
    file_put_contents($migration, $published);

    config(['database.default' => 'sqlite', 'database.connections.sqlite.database' => ':memory:']);
    $migrated = $this->app->make(ConsoleKernel::class)->handle(new ArrayInput(['command' => 'migrate', '--force' => true]), new BufferedOutput);

    expect($status)->toBe(0)
        ->and($published)->toBe(file_get_contents(dirname(__DIR__, 2).'/database/migrations/0001_01_01_000000_create_users_table.php'))
        ->and($output)->toContain('database/migrations/0001_01_01_000000_create_users_table.php')
        ->and($kept)->toEndWith("// mine\n")
        ->and($migrated)->toBe(0)
        ->and(app('db.schema')->hasTable('users'))->toBeTrue()
        ->and(app('db.schema')->hasTable('password_reset_tokens'))->toBeTrue()
        ->and(app('db.schema')->hasTable('sessions'))->toBeTrue();
});
