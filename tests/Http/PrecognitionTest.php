<?php

namespace Venusian\Serve\Tests\Http;

use Mariner\Core\Http\Kernel;
use Mariner\Core\Testing\TestCase;
use Mariner\Http\Middleware\HandlePrecognitiveRequests;
use Mariner\Http\Request;
use Mariner\Routing\CallableDispatcher;
use Mariner\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Mariner\Routing\Contracts\ControllerDispatcher as ControllerDispatcherContract;
use Mariner\Routing\ControllerDispatcher;
use Venusian\Serve\Tests\Fixtures\LoopPause;
use Venusian\Serve\Tests\Fixtures\NameRequest;
use Venusian\Serve\Tests\Fixtures\PrecognitionController;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Core\RenderedInstance;

/** Precognition through the kernel: the route resolves (and validates) its parameters, then answers 204 without its action. */
class PrecognitionTest extends TestCase
{
    public function createApplication(): RenderedInstance
    {
        $app = WebApp::boot();
        $app->registerInstance('env', 'testing');
        $app['config']->set('session.driver', 'array');
        PrecognitionController::$ran = 0;

        $router = $app['router'];
        $router->post('closure', function (NameRequest $request) {
            PrecognitionController::$ran++;

            return 'stored';
        })->middleware('precognitive');
        $router->post('controller', [PrecognitionController::class, 'store'])->middleware('precognitive');
        $router->post('missing', [PrecognitionController::class, 'nope'])->middleware('precognitive');

        return $app;
    }

    public function test_a_valid_precognitive_request_answers_204_without_running_the_action(): void
    {
        foreach (['closure', 'controller'] as $uri) {
            $this->withPrecognition()->postJson($uri, ['name' => 'Ada', 'email' => 'ada@example.test'])
                ->assertNoContent()
                ->assertHeader('Precognition', 'true')
                ->assertHeader('Precognition-Success', 'true');
        }

        $this->assertSame(0, PrecognitionController::$ran);
    }

    public function test_an_invalid_precognitive_request_answers_with_the_errors(): void
    {
        $this->withPrecognition()->postJson('controller', ['name' => 'Ada'])
            ->assertStatus(422)
            ->assertHeader('Precognition', 'true')
            ->assertJsonValidationErrors('email')
            ->assertHeaderMissing('Precognition-Success');

        $this->assertSame(0, PrecognitionController::$ran);
    }

    public function test_precognition_validates_only_the_fields_it_is_asked_about(): void
    {
        $this->withPrecognition()
            ->withHeader('Precognition-Validate-Only', 'name')
            ->postJson('controller', ['name' => 'Ada'])
            ->assertNoContent();
    }

    public function test_a_normal_request_runs_the_action_and_varies_on_precognition(): void
    {
        $this->postJson('controller', ['name' => 'Ada', 'email' => 'ada@example.test'])
            ->assertOk()
            ->assertSee('stored')
            ->assertHeader('Vary', 'Precognition')
            ->assertHeaderMissing('Precognition');

        $this->assertSame(1, PrecognitionController::$ran);
    }

    public function test_a_missing_controller_method_is_reported(): void
    {
        $this->withoutExceptionHandling();

        $this->assertThrows(
            fn () => $this->withPrecognition()->postJson('missing', ['name' => 'Ada', 'email' => 'ada@example.test']),
            \RuntimeException::class,
            'Attempting to predict the outcome of the ['.PrecognitionController::class.'::nope()] method but the method is not defined.'
        );
    }

    public function test_inline_validation_in_the_action_is_not_reached_under_precognition(): void
    {
        $this->app['router']->post('inline', fn (Request $request) => $request->validate(['name' => 'required', 'email' => 'required']))->middleware('precognitive');

        $this->withPrecognition()->postJson('inline', [])->assertNoContent();
        $this->flushHeaders()->postJson('inline', ['name' => 'Ada'])->assertStatus(422)->assertJsonValidationErrors('email');
    }

    public function test_request_validate_on_a_precognitive_request_checks_only_the_requested_fields(): void
    {
        $request = Request::create('/', 'POST', ['name' => 'Ada'], [], [], ['HTTP_PRECOGNITION' => 'true', 'HTTP_PRECOGNITION_VALIDATE_ONLY' => 'name']);
        $request->attributes->set('precognitive', true);

        // email is required but not asked about: only name is checked, and a pass ends the request with 204.
        try {
            $request->validate(['name' => 'required', 'email' => 'required']);
            $this->fail('A passing precognitive validation should end the request.');
        } catch (\Symfony\Component\HttpKernel\Exception\HttpException $e) {
            $this->assertSame(204, $e->getStatusCode());
            $this->assertSame('true', $e->getHeaders()['Precognition-Success'] ?? null);
        }

        // Asked about email too, the missing email fails it.
        $request->headers->set('Precognition-Validate-Only', 'name,email');
        $this->assertThrows(fn () => $request->validate(['name' => 'required', 'email' => 'required']), \Mariner\Validation\ValidationException::class);

        $plain = Request::create('/', 'POST', ['name' => 'Ada']);
        $this->assertThrows(fn () => $plain->validate(['name' => 'required', 'email' => 'required']), \Mariner\Validation\ValidationException::class);
    }

    public function test_a_precognitive_request_does_not_reach_a_normal_one_running_beside_it(): void
    {
        $loop = $this->app['event-loop'];
        $kernel = $this->app->make(Kernel::class);
        $events = [];

        // A middleware that hands the loop over, so the two requests interleave mid-pipeline.
        $this->app['router']->aliasMiddleware('yield', YieldingMiddleware::class);
        $this->app['router']->post('both', [PrecognitionController::class, 'store'])->middleware(['precognitive', 'yield']);

        $request = function (bool $precognitive) use ($loop, $kernel, &$events) {
            return $loop->async(function () use ($precognitive, $kernel, &$events) {
                // Each request gets its own scope, as `php computer serve` gives each its fiber.
                $this->app->forgetScopedInstances();
                $headers = ['HTTP_ACCEPT' => 'application/json'] + ($precognitive ? ['HTTP_PRECOGNITION' => 'true'] : []);
                $response = $kernel->handle(Request::create('/both', 'POST', ['name' => 'Ada', 'email' => 'ada@example.test'], [], [], $headers));
                $events[] = ($precognitive ? 'precognitive' : 'normal').':'.$response->getStatusCode();
            });
        };

        $precognitive = $request(true);
        $normal = $request(false);
        $loop->await($precognitive);
        $loop->await($normal);

        $this->assertEqualsCanonicalizing(['precognitive:204', 'normal:200'], $events);
        $this->assertSame(1, PrecognitionController::$ran);
        $this->assertGreaterThan(0, YieldingMiddleware::$yields);
    }

    public function test_precognition_runs_before_authorization_whatever_order_the_route_lists_them_in(): void
    {
        $this->app->make(\Mariner\Contracts\Auth\Access\Gate::class)->define('alwaysDeny', fn () => false);
        $this->app['router']->post('denied', fn () => 'ran')->middleware(['can:alwaysDeny', 'precognitive']);

        $this->withPrecognition()->postJson('denied', [])
            ->assertForbidden()
            ->assertHeader('Precognition', 'true')
            ->assertHeader('Vary', 'Precognition');
    }

    public function test_an_app_can_run_the_real_action_for_its_own_precognitive_requests_only(): void
    {
        $loop = $this->app['event-loop'];
        $kernel = $this->app->make(Kernel::class);
        $defaults = [$this->app->make(CallableDispatcherContract::class), $this->app->make(ControllerDispatcherContract::class)];
        $seen = [];

        $this->app['router']->aliasMiddleware('run-the-action', RunsTheActionUnderPrecognition::class);
        $this->app['router']->post('invoking', function () use ($defaults, &$seen) {
            LoopPause::yield();
            // While this precognitive request is suspended, the container's dispatchers are still the defaults.
            $seen[] = [$this->app->make(CallableDispatcherContract::class), $this->app->make(ControllerDispatcherContract::class)] === $defaults;

            return 'ran';
        })->middleware('run-the-action');

        $request = function (string $uri) use ($loop, $kernel, &$seen) {
            return $loop->async(function () use ($uri, $kernel, &$seen) {
                $this->app->forgetScopedInstances();
                $headers = ['HTTP_ACCEPT' => 'application/json', 'HTTP_PRECOGNITION' => 'true'];
                $response = $kernel->handle(Request::create($uri, 'POST', ['name' => 'Ada', 'email' => 'ada@example.test'], [], [], $headers));
                $seen[] = $uri.':'.$response->getStatusCode().':'.$response->getContent();
            });
        };

        $invoking = $request('/invoking');
        $other = $request('/closure');
        $loop->await($invoking);
        $loop->await($other);

        // The app's request ran its action; the other precognitive request, on the default middleware, did not.
        $this->assertContains('/invoking:200:ran', $seen);
        $this->assertContains('/closure:204:', $seen);
        $this->assertNotContains(false, $seen);
        $this->assertSame(0, PrecognitionController::$ran);
        $this->assertSame($defaults, [$this->app->make(CallableDispatcherContract::class), $this->app->make(ControllerDispatcherContract::class)]);
    }

    public function test_laravel_style_rebinding_in_the_hook_is_refused_loudly_and_undone(): void
    {
        $defaults = [$this->app->make(CallableDispatcherContract::class), $this->app->make(ControllerDispatcherContract::class)];
        $this->app['router']->aliasMiddleware('rebinds', RebindsLikeLaravel::class);
        $this->app['router']->post('rebinding', fn () => 'ran')->middleware('rebinds');

        $this->withoutExceptionHandling();
        $this->assertThrows(
            fn () => $this->withPrecognition()->postJson('rebinding', []),
            \LogicException::class,
            'call $this->dispatchUsing($request, ...) instead'
        );

        $this->assertSame($defaults, [$this->app->make(CallableDispatcherContract::class), $this->app->make(ControllerDispatcherContract::class)]);
    }
}

/** An app's middleware that runs the real action on its precognitive requests, the fiber-safe way. */
class RunsTheActionUnderPrecognition extends HandlePrecognitiveRequests
{
    protected function prepareForPrecognition($request)
    {
        parent::prepareForPrecognition($request);

        $this->dispatchUsing(
            $request,
            callable: fn ($app) => new CallableDispatcher($app),
            controller: fn ($app) => new ControllerDispatcher($app),
        );
    }
}

/** The same, the way Laravel apps write it: a global binding, which Mariner refuses. */
class RebindsLikeLaravel extends HandlePrecognitiveRequests
{
    protected function prepareForPrecognition($request)
    {
        parent::prepareForPrecognition($request);

        app()->bind(CallableDispatcherContract::class, fn ($app) => new CallableDispatcher($app));
    }
}

/** Yields to the loop once, between the request's middleware and its route. */
class YieldingMiddleware
{
    public static int $yields = 0;

    public function handle($request, $next)
    {
        static::$yields++;
        LoopPause::yield();

        return $next($request);
    }
}
