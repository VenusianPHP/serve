<?php

namespace Venusian\Serve\Tests\Integration;

use Carbon\Carbon;
use DateTimeInterface;
use Mariner\Auth\User;
use Mariner\Core\Testing\TestCase;
use Mariner\Routing\RouteCache;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Filesystem\Filesystem;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Voyager\Contracts\Console\Kernel;
use Voyager\Core\RenderedInstance;
use Voyager\Database\Testing\RefreshDatabase;
use Voyager\Database\Testing\RefreshDatabaseState;

/**
 * The base of the integration tests: the fixture app, driven through Mariner's HTTP kernel.
 *
 * An app's own phpunit.xml sets APP_ENV=testing and its session and cache drivers; the shared fixture sets none,
 * so this app does (CSRF would otherwise reject every POST, and the sqlite session file does not exist).
 */
class IntegrationTestCase extends TestCase
{
    /** Where this test's views are compiled: its own directory, so no test sees another's and the fixture app stays clean. */
    private string $compiledViews;

    /** The migration sets (directories under tests/Integration/migrations) the tests of the running file migrate. */
    private static array $migrationSets = [];

    /**
     * Choose the migration sets the tests of a file migrate, Testbench's #[WithMigration]. Call it from the file's
     * beforeAll() and again, with no sets, from its afterAll(): the next file migrates from scratch.
     */
    public static function withMigrations(string ...$sets): void
    {
        self::$migrationSets = $sets;
        RefreshDatabaseState::$migrated = false;
    }

    /** The copies of the fixture app a test wrote its routes into; removed with the test. */
    private array $appCopies = [];

    public function createApplication(): RenderedInstance
    {
        $this->compiledViews = sys_get_temp_dir().'/serve-views-'.bin2hex(random_bytes(6));
        mkdir($this->compiledViews);

        $app = $this->configureApplication(WebApp::boot());

        // A bare app, as Laravel's integration tests run in: the fixture's own routes would answer the same URIs.
        $app['router']->setRoutes(new RefreshingRouteCollection);

        return $app;
    }

    /** What a Laravel test app gets from its phpunit.xml: the testing environment, and array session and cache drivers, and a user model to authenticate. */
    private function configureApplication(RenderedInstance $app): RenderedInstance
    {
        $app['config']->set('view.compiled', $this->compiledViews);
        $app['config']->set('session.driver', 'array');
        $app['config']->set('cache.default', 'array');
        $app['config']->set('auth.providers.users.model', User::class);
        $app->registerInstance('env', 'testing');

        $this->configureDatabase($app);

        return $app;
    }

    /**
     * A sqlite database of the test run's own, one file per set of migrations, which RefreshDatabase migrates once and
     * rolls each test back from. Only the tests that use RefreshDatabase touch it.
     */
    private function configureDatabase(RenderedInstance $app): void
    {
        $database = sys_get_temp_dir().'/serve-integration-'.getmypid().'-'.md5(implode(',', self::$migrationSets)).'.sqlite';

        if (! is_file($database)) {
            touch($database);
            register_shutdown_function(fn () => is_file($database) && unlink($database));
        }

        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => $database, 'prefix' => '', 'foreign_key_constraints' => true]);
        $app['config']->set('database.default', 'testing');

        foreach (self::$migrationSets as $set) {
            $app['migrator']->path(__DIR__.'/migrations/'.$set);
        }
    }

    /**
     * Write routes into a copy of the app, cache them with route:cache, and run the test on an app booted from that cache.
     *
     * This is Testbench's defineCacheRoutes(), on Mariner's own cache: the routes are cached by the command and loaded
     * by the route cache, not through a Laravel routes-v7 file written by the test.
     */
    protected function defineCacheRoutes(string $routes): void
    {
        $key = $this->app['config']->get('app.key');
        $refreshesDatabase = in_array(RefreshDatabase::class, class_uses_recursive($this), true);

        // The first app's transaction would hold the sqlite file against the apps booted below.
        if ($refreshesDatabase) {
            $this->tearDownRefreshDatabase();
        }

        $this->appCopies[] = $root = WebApp::copy();
        file_put_contents($root.'/routes/cached.php', $routes);
        file_put_contents($root.'/config/routing.php', "<?php\n\nreturn ['web' => '{$root}/routes/cached.php', 'api' => null];\n");

        $boot = fn () => tap($this->configureApplication(WebApp::bootFrom($root)), fn ($app) => $app['config']->set('app.key', $key));

        $output = new BufferedOutput;
        $exitCode = $boot()->make(Kernel::class)->handle(new ArrayInput(['command' => 'route:cache']), $output);
        $this->assertSame(0, $exitCode, $output->fetch());

        WebApp::tearDown($this);
        $this->app = $boot();

        // The app that booted from the cache has a connection of its own: it rolls back what the test writes, like the first.
        if ($refreshesDatabase) {
            $this->beginDatabaseTransaction();
        }

        $this->assertTrue($this->app->make(RouteCache::class)->isCached(), 'The app did not boot from the route cache.');
    }

    /**
     * Boot the app again, for a test that sets the environment the app reads at boot (APP_ROUTES_CACHE) after the
     * app was created. The test runs on the new app.
     */
    protected function rebootApplication(): void
    {
        $refreshesDatabase = in_array(RefreshDatabase::class, class_uses_recursive($this), true);

        if ($refreshesDatabase) {
            $this->tearDownRefreshDatabase();
        }

        WebApp::tearDown($this);
        $this->app = $this->configureApplication(WebApp::boot());

        if ($refreshesDatabase) {
            $this->beginDatabaseTransaction();
        }
    }

    protected function tearDown(): void
    {
        $files = new Filesystem;
        $files->deleteDirectory($this->compiledViews);

        foreach ($this->appCopies as $root) {
            $files->deleteDirectory($root);
        }

        parent::tearDown();
    }

    /** Freeze the clock at the start of the current second, as Laravel's InteractsWithTime does. */
    protected function freezeSecond(): Carbon
    {
        return $this->travelTo(Carbon::now()->startOfSecond());
    }

    /** Move the clock to a date. Pest.php puts it back after every test. */
    protected function travelTo(DateTimeInterface|string $date): Carbon
    {
        Carbon::setTestNow($date);

        return Carbon::now();
    }
}
