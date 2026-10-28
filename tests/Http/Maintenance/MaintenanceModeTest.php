<?php

namespace Venusian\Serve\Tests\Http\Maintenance;

use Carbon\Carbon;
use DateTimeInterface;
use Mariner\Contracts\Http\MaintenanceMode;
use Mariner\Core\Testing\TestCase;
use Mariner\Http\Events\MaintenanceModeDisabled;
use Mariner\Http\Events\MaintenanceModeEnabled;
use Mariner\Http\Maintenance\CacheBasedMaintenanceMode;
use Mariner\Http\Maintenance\MaintenanceModeBypassCookie;
use Mariner\Http\Middleware\PreventRequestsDuringMaintenance;
use Mockery;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Component\HttpFoundation\Cookie;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Contracts\Cache\Factory;
use Voyager\Contracts\Cache\Repository;
use Voyager\Core\RenderedInstance;
use Voyager\Filesystem\Filesystem;

/** Laravel v13.37.0 tests/Integration/Foundation/MaintenanceModeTest, through Mariner's harness. */
class MaintenanceModeTest extends TestCase
{
    private string $root;

    public function createApplication(): RenderedInstance
    {
        // A copy per test, so storage/framework/down never outlives the test that wrote it.
        $app = WebApp::bootFrom($this->root = WebApp::copy());
        $app->registerInstance('env', 'testing');
        $app['config']->set('session.driver', 'array');

        return $app;
    }

    protected function tearDown(): void
    {
        $root = $this->root;
        PreventRequestsDuringMaintenance::flushState();

        parent::tearDown();

        (new Filesystem)->deleteDirectory($root);
    }

    private function route(string $uri): void
    {
        $this->app['router']->get($uri, fn () => 'Hello World')->middleware(PreventRequestsDuringMaintenance::class);
    }

    private function down(array $payload): void
    {
        file_put_contents(storage_path('framework/down'), json_encode($payload));
    }

    private function downFile(): array
    {
        return json_decode(file_get_contents(storage_path('framework/down')), true);
    }

    public function test_basic_maintenance_mode_response(): void
    {
        $this->down(['retry' => 60, 'refresh' => 60]);
        $this->route('/foo');

        $this->get('/foo')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertHeader('Refresh', '60');
    }

    public function test_cache_maintenance_mode_allows_request_when_deactivated_while_reading_payload(): void
    {
        $cache = Mockery::mock(Factory::class, Repository::class);
        $cache->shouldReceive('store')->with('maintenance')->andReturnSelf();
        $cache->shouldReceive('has')->with('framework:down')->andReturn(true, false);
        $cache->shouldReceive('get')->once()->with('framework:down')->andReturnNull();

        $this->app->registerInstance(MaintenanceMode::class, new CacheBasedMaintenanceMode($cache, 'maintenance', 'framework:down'));
        $this->app['router']->get('/foo', fn () => 'Hello World');

        $this->get('/foo')->assertOk()->assertSeeText('Hello World');
    }

    public function test_maintenance_mode_can_have_custom_status(): void
    {
        $this->down(['retry' => 60, 'status' => 200]);
        $this->route('/foo');

        $this->get('/foo')->assertStatus(200)->assertHeader('Retry-After', '60');
    }

    public function test_maintenance_mode_can_have_custom_template(): void
    {
        $this->down(['retry' => 60, 'template' => 'Rendered Content']);
        $this->route('/foo');

        $response = $this->get('/foo');

        $response->assertStatus(503)->assertHeader('Retry-After', '60');
        $this->assertSame('Rendered Content', $response->original);
    }

    public function test_maintenance_mode_does_not_use_custom_template_for_json_requests(): void
    {
        $this->down(['retry' => 60, 'template' => 'Rendered Content']);
        $this->route('/foo');

        $this->getJson('/foo')
            ->assertStatus(503)
            ->assertHeader('Retry-After', '60')
            ->assertJson(['message' => 'Service Unavailable']);
    }

    public function test_maintenance_mode_does_not_redirect_json_requests(): void
    {
        $this->down(['redirect' => '/maintenance']);
        $this->route('/foo');

        $this->getJson('/foo')->assertStatus(503)->assertJson(['message' => 'Service Unavailable']);
    }

    public function test_prerendered_maintenance_file_allows_json_requests_to_reach_framework(): void
    {
        $this->down(['template' => 'Rendered Content']);
        file_put_contents(
            storage_path('framework/maintenance.php'),
            file_get_contents(dirname(__DIR__, 3).'/src/Mariner/Core/Console/stubs/maintenance-mode.stub')
        );

        $server = $_SERVER;

        try {
            $_SERVER['REQUEST_URI'] = '/foo';
            $_SERVER['HTTP_ACCEPT'] = 'application/json';
            ob_start();
            include storage_path('framework/maintenance.php');
            $output = ob_get_clean();
        } finally {
            $_SERVER = $server;
        }

        $this->assertSame('', $output);
    }

    public function test_maintenance_mode_can_redirect_with_bypass_cookie(): void
    {
        $this->down(['retry' => 60, 'secret' => 'foo', 'template' => 'Rendered Content']);
        $this->route('/foo');

        $this->get('/foo')->assertStatus(302)->assertCookie(MaintenanceModeBypassCookie::NAME, null, false);
    }

    public function test_maintenance_mode_can_be_bypassed_with_valid_cookie(): void
    {
        $this->down(['retry' => 60, 'secret' => 'foo']);
        $cookie = MaintenanceModeBypassCookie::create('foo');
        $this->route('/test');

        $response = $this->withUnencryptedCookies([MaintenanceModeBypassCookie::NAME => $cookie->getValue()])->get('/test');

        $response->assertStatus(200);
        $this->assertSame('Hello World', $response->original);
    }

    public function test_maintenance_mode_can_be_bypassed_on_excluded_urls(): void
    {
        $this->app->registerInstance(PreventRequestsDuringMaintenance::class, new class($this->app) extends PreventRequestsDuringMaintenance
        {
            protected $except = ['/test'];
        });

        $this->down(['retry' => 60]);
        $this->app['router']->get('/test', fn () => 'Hello World');

        $response = $this->get('/test');

        $response->assertStatus(200);
        $this->assertSame('Hello World', $response->original);
    }

    public function test_maintenance_mode_cant_be_bypassed_with_invalid_cookie(): void
    {
        $this->down(['retry' => 60, 'secret' => 'foo']);
        $cookie = MaintenanceModeBypassCookie::create('test-key');
        $this->route('/test');

        $this->withUnencryptedCookies([MaintenanceModeBypassCookie::NAME => $cookie->getValue()])
            ->get('/test')
            ->assertStatus(503);
    }

    public function test_can_create_bypass_cookies(): void
    {
        $cookie = MaintenanceModeBypassCookie::create('test-key');

        $this->assertInstanceOf(Cookie::class, $cookie);
        $this->assertSame('venusian_maintenance', $cookie->getName());
        $this->assertTrue(MaintenanceModeBypassCookie::isValid($cookie->getValue(), 'test-key'));
        $this->assertFalse(MaintenanceModeBypassCookie::isValid($cookie->getValue(), 'wrong-key'));

        Carbon::setTestNow(Carbon::now()->addMonths(6));
        $this->assertFalse(MaintenanceModeBypassCookie::isValid($cookie->getValue(), 'test-key'));
        Carbon::setTestNow();
    }

    public function test_bypass_cookie_with_malformed_mac_is_invalid(): void
    {
        foreach ([['mac' => []], ['mac' => ['nested']], ['expires_at' => 9999999999]] as $payload) {
            $cookie = base64_encode(json_encode(array_merge(['expires_at' => 9999999999], $payload)));

            $this->assertFalse(MaintenanceModeBypassCookie::isValid($cookie, 'test-key'));
        }
    }

    public function test_dispatch_event_when_maintenance_mode_is_enabled(): void
    {
        $fired = [];
        $this->app['signals']->listen(MaintenanceModeEnabled::class, function () use (&$fired) {
            $fired[] = 'enabled';
        });

        $this->artisan('down')->assertOk();

        $this->assertSame(['enabled'], $fired);
    }

    public function test_dispatch_event_when_maintenance_mode_is_disabled(): void
    {
        $this->down(['retry' => 60]);
        $fired = [];
        $this->app['signals']->listen(MaintenanceModeDisabled::class, function () use (&$fired) {
            $fired[] = 'disabled';
        });

        $this->artisan('up')->assertOk();

        $this->assertSame(['disabled'], $fired);
        $this->assertFileDoesNotExist(storage_path('framework/down'));
    }

    #[DataProvider('retryAfterDatetimeProvider')]
    public function test_maintenance_mode_retry_can_accept_datetime(string $datetime): void
    {
        $this->artisan('down', ['--retry' => $datetime]);

        $this->assertSame(Carbon::parse($datetime)->format(DateTimeInterface::RFC7231), $this->downFile()['retry']);
    }

    public static function retryAfterDatetimeProvider(): array
    {
        return [
            'ISO 8601 format' => ['2023-01-08 00:00:00'],
            'natural language' => ['tomorrow 14:00'],
            'relative time' => ['+2 hours'],
        ];
    }

    public function test_maintenance_mode_retry_datetime_is_converted_to_gmt(): void
    {
        $timezone = date_default_timezone_get();
        date_default_timezone_set('Europe/Berlin');

        try {
            $this->artisan('down', ['--retry' => '2026-12-31 15:30:00']);
            $this->assertSame('Thu, 31 Dec 2026 14:30:00 GMT', $this->downFile()['retry']);

            $this->artisan('down', ['--retry' => '2026-12-31T15:30:00+02:00']);
            $this->assertSame('Thu, 31 Dec 2026 13:30:00 GMT', $this->downFile()['retry']);
        } finally {
            date_default_timezone_set($timezone);
        }
    }

    public function test_maintenance_mode_retry_with_http_date_header(): void
    {
        $expectedHeader = Carbon::now()->addWeek()->format(DateTimeInterface::RFC7231);
        $this->down(['retry' => $expectedHeader]);
        $this->route('/foo');

        $this->get('/foo')->assertStatus(503)->assertHeader('Retry-After', $expectedHeader);
    }

    public function test_maintenance_mode_retry_with_invalid_datetime_returns_null(): void
    {
        $this->artisan('down', ['--retry' => 'not-a-valid-date']);

        $this->assertNull($this->downFile()['retry']);
    }

    public function test_maintenance_mode_retry_with_at_timestamp_notation(): void
    {
        $futureTimestamp = time() + 3600;

        $this->artisan('down', ['--retry' => '@'.$futureTimestamp]);

        $this->assertSame(Carbon::createFromTimestamp($futureTimestamp)->format(DateTimeInterface::RFC7231), $this->downFile()['retry']);
    }

    public function test_maintenance_mode_can_be_refreshed_with_new_options(): void
    {
        $this->artisan('down', ['--retry' => 60])->expectsOutputToContain('Application is now in maintenance mode.');
        $this->assertSame(60, $this->downFile()['retry']);

        $this->artisan('down', ['--retry' => 120])->expectsOutputToContain('Maintenance mode options updated.');
        $this->assertSame(120, $this->downFile()['retry']);
    }

    public function test_maintenance_mode_respects_bootstrap_configured_excluded_paths(): void
    {
        PreventRequestsDuringMaintenance::except(['/api/*', '/webhooks/*']);

        $this->artisan('down');

        $this->assertSame(['/api/*', '/webhooks/*'], $this->downFile()['except']);
    }

    public function test_the_global_middleware_blocks_routes_that_name_no_middleware(): void
    {
        $this->down(['status' => 503]);
        $this->app['router']->get('/plain', fn () => 'Hello World');

        $this->get('/plain')->assertStatus(503);
    }

    public function test_the_secret_path_sets_a_cookie_that_lets_later_requests_through(): void
    {
        $this->down(['secret' => 'let-me-in']);
        $this->app['router']->get('/dashboard', fn () => 'Dashboard');

        $bypass = $this->get('/let-me-in');
        $bypass->assertRedirect('/')->assertCookie(MaintenanceModeBypassCookie::NAME, null, false);

        $cookie = collect($bypass->headers->getCookies())->first(fn ($c) => $c->getName() === MaintenanceModeBypassCookie::NAME);

        $this->withUnencryptedCookies([MaintenanceModeBypassCookie::NAME => $cookie->getValue()])
            ->get('/dashboard')
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function test_down_prerenders_the_view_it_is_given(): void
    {
        file_put_contents(resource_path('views/down.blade.php'), 'Back in {{ $retryAfter }} seconds');

        $this->artisan('down', ['--render' => 'down', '--retry' => 60])->assertOk();

        $this->assertSame('Back in 60 seconds', $this->downFile()['template']);
        $this->assertFileExists(storage_path('framework/maintenance.php'));
    }

    public function test_down_with_secret_prints_the_bypass_url(): void
    {
        $this->artisan('down', ['--secret' => 'open-sesame'])
            ->expectsOutputToContain('/open-sesame')
            ->assertOk();

        $this->assertSame('open-sesame', $this->downFile()['secret']);
    }

    public function test_up_when_already_up_says_so(): void
    {
        $this->artisan('up')->expectsOutputToContain('Application is already up.')->assertOk();
    }

    /**
     * Serve one request through the pre-boot page in its own PHP process, as a front controller would
     * (the page exits after answering). Returns [status, body].
     *
     * @param  array<string, string>  $cookies
     */
    private function preBoot(array $payload, array $cookies = []): array
    {
        $this->down($payload);
        file_put_contents(
            storage_path('framework/maintenance.php'),
            file_get_contents(dirname(__DIR__, 3).'/src/Mariner/Core/Console/stubs/maintenance-mode.stub')
        );

        $script = sprintf(
            '$_SERVER["REQUEST_URI"] = "/foo"; $_SERVER["HTTP_ACCEPT"] = "text/html"; $_COOKIE = %s;'
            .' register_shutdown_function(fn () => print("\n[status ".http_response_code()."]"));'
            .' require %s; print("\n[reached the framework]");',
            var_export($cookies, true),
            var_export(storage_path('framework/maintenance.php'), true)
        );

        $output = (string) shell_exec(PHP_BINARY.' -d display_errors=stderr -r '.escapeshellarg($script).' 2>&1');

        preg_match('/\[status (\d+)\]$/', $output, $status);

        return [(int) ($status[1] ?? 0), trim(preg_replace('/\n\[status \d+\]$/', '', $output))];
    }

    public function test_the_prerendered_file_serves_the_template_to_a_browser(): void
    {
        $this->assertSame([503, 'Rendered Content'], $this->preBoot(['template' => 'Rendered Content', 'status' => 503]));
    }

    public function test_the_prerendered_file_lets_a_valid_bypass_cookie_through(): void
    {
        $cookie = MaintenanceModeBypassCookie::create('the-secret');

        [, $body] = $this->preBoot(['template' => 'Rendered Content', 'secret' => 'the-secret'], [MaintenanceModeBypassCookie::NAME => $cookie->getValue()]);

        $this->assertStringContainsString('[reached the framework]', $body);
    }

    public function test_the_prerendered_file_answers_forged_non_string_macs_with_the_maintenance_page(): void
    {
        foreach ([[], 1, 1.5, true, null] as $mac) {
            $forged = base64_encode(json_encode(['expires_at' => 9999999999, 'mac' => $mac]));

            $this->assertSame(
                [503, 'Rendered Content'],
                $this->preBoot(['template' => 'Rendered Content', 'secret' => 'the-secret', 'status' => 503], [MaintenanceModeBypassCookie::NAME => $forged]),
                'mac '.json_encode($mac)
            );
        }
    }
}
