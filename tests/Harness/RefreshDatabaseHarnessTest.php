<?php

namespace Venusian\Serve\Tests\Harness;

use Mariner\Core\Testing\TestCase;
use Mariner\Http\Request;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Core\RenderedInstance;
use Voyager\Database\Testing\Concerns\InteractsWithDatabase;
use Voyager\Database\Testing\RefreshDatabase;
use Voyager\Database\Testing\RefreshDatabaseState;

/** The framework's RefreshDatabase through the harness: migrated once, each test rolled back. */
class RefreshDatabaseHarnessTest extends TestCase
{
    use InteractsWithDatabase, RefreshDatabase;

    private static ?string $database = null;

    public function createApplication(): RenderedInstance
    {
        // One database file for the class, as an app's suite shares one database.
        self::$database ??= tap(sys_get_temp_dir().'/serve-refresh-'.getmypid().'.sqlite', 'touch');

        $app = WebApp::boot();
        $app->registerInstance('env', 'testing');
        $app['config']->set('session.driver', 'array');
        $app['config']->set('database.connections.harness', ['driver' => 'sqlite', 'database' => self::$database, 'prefix' => '']);
        $app['config']->set('database.default', 'harness');
        $app['migrator']->path(__DIR__.'/migrations');

        $app['router']->post('/posts', function (Request $request) {
            $request->validate(['title' => 'required']);
            app('db')->connection()->table('posts')->insert(['title' => $request->input('title')]);

            return response('created', 201);
        });

        return $app;
    }

    public static function tearDownAfterClass(): void
    {
        RefreshDatabaseState::$migrated = false;

        if (self::$database && is_file(self::$database)) {
            unlink(self::$database);
        }

        self::$database = null;

        parent::tearDownAfterClass();
    }

    public function test_a_request_writes_inside_the_test_transaction(): void
    {
        $this->post('/posts', ['title' => 'Hello'])->assertStatus(201);

        $this->assertDatabaseHas('posts', ['title' => 'Hello'])
            ->assertDatabaseCount('posts', 1);
    }

    public function test_the_next_test_starts_from_a_rolled_back_database(): void
    {
        $this->assertDatabaseEmpty('posts');
        $this->assertTrue(RefreshDatabaseState::$migrated);
    }

    public function test_validation_failures_write_nothing(): void
    {
        $this->post('/posts', [])->assertSessionHasErrors('title');

        $this->assertDatabaseEmpty('posts');
    }
}
