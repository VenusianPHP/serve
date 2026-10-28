<?php

namespace Venusian\Serve\Tests\Harness;

use Mariner\Auth\GenericUser;
use Mariner\Core\Testing\TestCase;
use Mariner\Core\Testing\WithoutMiddleware;
use Mariner\Http\Events\RequestHandled;
use Mariner\Http\Request;
use Mariner\Validation\ValidationException;
use RuntimeException;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Core\RenderedInstance;

/** The harness driving the fixture app through Mariner's HTTP kernel. */
class HarnessTest extends TestCase
{
    use BootsHarnessApp;

    public function test_get_runs_the_route_through_the_kernel(): void
    {
        $this->get('/hello')->assertOk()->assertSee('hello');
    }

    public function test_headers_reach_the_request(): void
    {
        $this->withHeaders(['X-Probe' => 'yes'])->get('/header')->assertSee('yes');
    }

    public function test_cookies_are_encrypted_on_the_way_in_and_read_back(): void
    {
        $this->withCookie('flavor', 'oat')->get('/cookie')->assertSee('oat');
    }

    public function test_session_persists_across_calls_in_one_test(): void
    {
        $this->post('/session', ['step' => 'two'])->assertSee('stored');

        $this->get('/session')->assertSee('two');
        $this->withSession(['step' => 'three'])->get('/session')->assertSee('three');
    }

    public function test_acting_as_a_user(): void
    {
        $this->get('/me')->assertSee('guest');

        $this->actingAs(new GenericUser(['id' => 7]))->get('/me')->assertSee('7');
        $this->assertAuthenticated();
    }

    public function test_the_renderer_turns_exceptions_into_responses(): void
    {
        $this->get('/boom')->assertStatus(500);
        $this->post('/validate', [])->assertRedirect()->assertSessionHasErrors('name');
        $this->postJson('/validate', [])->assertStatus(422)->assertJsonValidationErrors('name');
    }

    public function test_without_exception_handling_rethrows(): void
    {
        $this->withoutExceptionHandling();

        $this->assertThrows(fn () => $this->get('/boom'), RuntimeException::class, 'boom');
        $this->assertInstanceOf(\Mariner\Testing\Concerns\WithoutExceptionHandlingHandler::class, $this->app->make(\Mariner\Contracts\Http\ExceptionRenderer::class));
    }

    public function test_handled_exceptions_still_render_without_exception_handling(): void
    {
        $this->handleValidationExceptions();

        $this->postJson('/validate', [])->assertStatus(422);
        $this->assertThrows(fn () => $this->get('/boom'), RuntimeException::class);
    }

    public function test_exception_handling_can_be_restored(): void
    {
        $this->withoutExceptionHandling()->withExceptionHandling();

        $this->get('/boom')->assertStatus(500);
    }

    public function test_json_requests(): void
    {
        $this->postJson('/api/echo', ['name' => 'ada'])->assertOk()->assertExactJson(['got' => 'ada']);
    }

    public function test_following_redirects(): void
    {
        $this->get('/hop')->assertRedirect('/hello');
        $this->followingRedirects()->get('/hop')->assertOk()->assertSee('hello');
    }

    public function test_terminate_runs_after_each_call(): void
    {
        $handled = 0;
        $this->app['signals']->listen(RequestHandled::class, function () use (&$handled) {
            $handled++;
        });

        $this->get('/hello');
        $this->get('/hello');

        $this->assertSame(2, $handled);
    }

    public function test_without_middleware_skips_the_web_group(): void
    {
        $this->withoutMiddleware();

        // No StartSession: the session the route reads was never started from the request's cookie.
        $this->get('/hello')->assertOk()->assertCookieMissing(config('session.cookie'));
        $this->withMiddleware()->get('/hello')->assertCookie(config('session.cookie'));
    }

    public function test_mocks_are_swapped_in_and_forgotten(): void
    {
        $original = $this->app->make('url');
        $this->mock('url', fn ($mock) => $mock->shouldReceive('to')->andReturn('mocked'));

        $this->assertSame('mocked', $this->app->make('url')->to('/'));

        $this->forgetMock('url');
        $this->assertSame($original, $this->app->make('url'));
    }

    public function test_views_render_through_the_harness(): void
    {
        $this->blade('<p>{{ $name }}</p>', ['name' => 'ada'])->assertSee('<p>ada</p>', false);
    }

    public function test_computer_commands_run_through_the_console_kernel(): void
    {
        $this->artisan('about', ['--only' => 'environment'])
            ->expectsOutputToContain('Environment')
            ->assertOk();

        $this->assertSame(0, $this->withoutMockingConsoleOutput()->artisan('about', ['--only' => 'environment']));
    }
}
