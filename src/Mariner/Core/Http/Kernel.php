<?php

namespace Mariner\Core\Http;

use Carbon\CarbonInterval;
use DateTimeInterface;
use InvalidArgumentException;
use Mariner\Contracts\Http\ExceptionRenderer;
use Mariner\Http\Events\RequestHandled;
use Mariner\Http\Request;
use Mariner\NutsAndBolts\Facades\Facade;
use Mariner\Routing\Pipeline;
use Mariner\Routing\Router;
use Symfony\Component\HttpFoundation\Response;
use Throwable;
use Voyager\Contracts\Core\FrameworkCore;
use Voyager\NutsAndBolts\Concerns\InteractsWithTime;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use WeakMap;

/**
 * Handles a web request: bootstraps the app, runs the request through the global middleware into
 * the router, and turns any exception into a response. One kernel serves every request, so
 * nothing about a request is kept on it but what is keyed by that request.
 */
class Kernel
{
    use InteractsWithTime;

    /**
     * The bootstrap classes for the application.
     *
     * @var list<class-string>
     */
    protected array $bootstrappers = [
        \Voyager\Core\Bootstrap\LoadEnvironmentVariables::class,
        \Voyager\Core\Bootstrap\LoadConfiguration::class,
        \Voyager\Core\Bootstrap\HandleExceptions::class,
        \Voyager\Core\Bootstrap\RegisterProviders::class,
        \Voyager\Core\Bootstrap\BootProviders::class,
    ];

    /**
     * The middleware every request runs through.
     *
     * @var list<class-string|string>
     */
    protected array $middleware = [
        \Mariner\Http\Middleware\InvokeDeferredCallbacks::class,
        \Mariner\Http\Middleware\TrustProxies::class,
        \Mariner\Http\Middleware\HandleCors::class,
        \Mariner\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Mariner\Http\Middleware\ValidatePostSize::class,
        \Mariner\Http\Middleware\TrimStrings::class,
        \Mariner\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * The route middleware groups.
     *
     * @var array<string, list<class-string|string>>
     */
    protected array $middlewareGroups = [
        'web' => [
            \Mariner\Cookie\Middleware\EncryptCookies::class,
            \Mariner\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Mariner\Session\Middleware\StartSession::class,
            \Mariner\View\Middleware\ShareErrorsFromSession::class,
            \Mariner\Http\Middleware\PreventRequestForgery::class,
            \Mariner\Routing\Middleware\SubstituteBindings::class,
        ],

        'api' => [
            \Mariner\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * The middleware aliases routes may name.
     *
     * @var array<string, class-string|string>
     */
    protected array $middlewareAliases = [
        'auth' => \Mariner\Auth\Middleware\Authenticate::class,
        'auth.basic' => \Mariner\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Mariner\Session\Middleware\AuthenticateSession::class,
        'cache.headers' => \Mariner\Http\Middleware\SetCacheHeaders::class,
        'can' => \Mariner\Auth\Middleware\Authorize::class,
        'guest' => \Mariner\Auth\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Mariner\Auth\Middleware\RequirePassword::class,
        'precognitive' => \Mariner\Http\Middleware\HandlePrecognitiveRequests::class,
        'signed' => \Mariner\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Mariner\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Mariner\Auth\Middleware\EnsureEmailIsVerified::class,
    ];

    /**
     * The order route middleware runs in, whatever order a route lists it.
     *
     * @var list<class-string>
     */
    protected array $middlewarePriority = [
        \Mariner\Http\Middleware\HandlePrecognitiveRequests::class,
        \Mariner\Cookie\Middleware\EncryptCookies::class,
        \Mariner\Cookie\Middleware\AddQueuedCookiesToResponse::class,
        \Mariner\Session\Middleware\StartSession::class,
        \Mariner\View\Middleware\ShareErrorsFromSession::class,
        \Mariner\Contracts\Auth\Middleware\AuthenticatesRequests::class,
        \Mariner\Routing\Middleware\ThrottleRequests::class,
        \Mariner\Routing\Middleware\ThrottleRequestsWithRedis::class,
        \Mariner\Contracts\Session\Middleware\AuthenticatesSessions::class,
        \Mariner\Routing\Middleware\SubstituteBindings::class,
        \Mariner\Auth\Middleware\Authorize::class,
    ];

    /**
     * Handlers for requests that take longer than their threshold, in milliseconds.
     *
     * @var list<array{threshold: float|int, handler: callable}>
     */
    protected array $requestLifecycleDurationHandlers = [];

    /**
     * When each request in flight started.
     *
     * @var \WeakMap<\Mariner\Http\Request, \Voyager\NutsAndBolts\DataObjects\Carbon>
     */
    protected WeakMap $started_at;

    public function __construct(
        protected readonly FrameworkCore $app,
        protected Router $router,
    ) {
        $this->started_at = new WeakMap;

        $this->syncMiddlewareToRouter();
    }

    /**
     * Handle the request.
     *
     * @param  \Mariner\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request): Response
    {
        $this->started_at[$request] = Carbon::now();

        try {
            $request->enableHttpMethodParameterOverride();

            return $this->sendRequestThroughRouter($request);
        } catch (Throwable $e) {
            return $this->app->make(ExceptionRenderer::class)->handle($request, $e);
        }
    }

    /**
     * Finish the request after its response was sent: announce it, run the terminable middleware
     * and the app's terminating callbacks, then the slow-request handlers.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->app['signals']->dispatch(new RequestHandled($request, $response));

        $this->terminateMiddleware($request, $response);

        $this->app->terminate();

        $started_at = $this->started_at[$request] ?? null;
        unset($this->started_at[$request]);

        if ($started_at === null) {
            return;
        }

        $started_at->setTimezone($this->app['config']->get('app.timezone') ?? 'UTC');

        foreach ($this->requestLifecycleDurationHandlers as ['threshold' => $threshold, 'handler' => $handler]) {
            $end ??= Carbon::now();

            if ($started_at->diffInMilliseconds($end) > $threshold) {
                $handler($started_at, $request, $response);
            }
        }
    }

    /**
     * Bootstrap the application, once.
     *
     * @return void
     */
    public function bootstrap(): void
    {
        if (! $this->app->hasBeenBootstrapped()) {
            $this->app->bootstrapWith($this->bootstrappers);

            // A kernel made before the app booted (public/index.php) autowired a router of its own; the routes are on the app's.
            if ($this->router !== ($router = $this->app->make('router'))) {
                $this->router = $router;
                $this->syncMiddlewareToRouter();
            }
        }
    }

    /**
     * Make the request the current one, then send it through the global middleware to the router.
     *
     * @param  \Mariner\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    protected function sendRequestThroughRouter(Request $request): Response
    {
        $this->bootstrap();

        $this->useRequest($request);

        return (new Pipeline($this->app))
            ->send($request)
            ->through($this->shouldSkipMiddleware() ? [] : $this->middleware)
            ->then(fn ($request) => $this->router->dispatch($request));
    }

    /**
     * Register the request in the current scope. A URL generator this scope already built holds the
     * request it was built with, so it gets this one (Laravel does that on rebinding 'request').
     *
     * @param  \Mariner\Http\Request  $request
     * @return void
     */
    protected function useRequest(Request $request): void
    {
        $this->app->registerInstance('request', $request);

        Facade::clearResolvedInstance('request');

        if ($this->app->isBound('url')) {
            $this->app->make('url')->setRequest($request);
        }
    }

    /**
     * Call terminate() on every terminable middleware the request ran through.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    protected function terminateMiddleware(Request $request, Response $response): void
    {
        $middlewares = $this->shouldSkipMiddleware() ? [] : array_merge(
            ($route = $request->route()) ? $this->router->gatherRouteMiddleware($route) : [],
            $this->middleware
        );

        foreach ($middlewares as $middleware) {
            if (! is_string($middleware)) {
                continue;
            }

            [$name] = $this->parseMiddleware($middleware);

            $instance = $this->app->make($name);

            if (method_exists($instance, 'terminate')) {
                $instance->terminate($request, $response);
            }
        }
    }

    /**
     * Determine if the app asked to run without middleware ('middleware.disable', for tests).
     *
     * @return bool
     */
    protected function shouldSkipMiddleware(): bool
    {
        return $this->app->isBound('middleware.disable')
            && $this->app->make('middleware.disable') === true;
    }

    /**
     * Run $handler for each request that takes longer than $threshold.
     *
     * @param  \DateTimeInterface|\Carbon\CarbonInterval|float|int  $threshold  milliseconds, or how long
     * @param  callable(\Voyager\NutsAndBolts\DataObjects\Carbon, \Mariner\Http\Request, \Symfony\Component\HttpFoundation\Response): mixed  $handler
     * @return void
     */
    public function whenRequestLifecycleIsLongerThan(DateTimeInterface|CarbonInterval|float|int $threshold, callable $handler): void
    {
        $threshold = $threshold instanceof DateTimeInterface
            ? $this->secondsUntil($threshold) * 1000
            : $threshold;

        $threshold = $threshold instanceof CarbonInterval
            ? $threshold->totalMilliseconds
            : $threshold;

        $this->requestLifecycleDurationHandlers[] = [
            'threshold' => $threshold,
            'handler' => $handler,
        ];
    }

    /**
     * When the request started, while it is in flight.
     *
     * @param  \Mariner\Http\Request|null  $request  the current request when null
     * @return \Voyager\NutsAndBolts\DataObjects\Carbon|null
     */
    public function requestStartedAt(?Request $request = null): ?Carbon
    {
        return $this->started_at[$request ?? $this->app->make('request')] ?? null;
    }

    /**
     * Split "name:param,param" into the name and its parameters.
     *
     * @param  string  $middleware
     * @return array{0: string, 1: list<string>}
     */
    protected function parseMiddleware(string $middleware): array
    {
        [$name, $parameters] = array_pad(explode(':', $middleware, 2), 2, []);

        if (is_string($parameters)) {
            $parameters = explode(',', $parameters);
        }

        return [$name, $parameters];
    }

    /**
     * Determine if the kernel has the global middleware.
     *
     * @param  string  $middleware
     * @return bool
     */
    public function hasMiddleware(string $middleware): bool
    {
        return in_array($middleware, $this->middleware);
    }

    /**
     * Add global middleware to the start of the stack.
     *
     * @param  string  $middleware
     * @return $this
     */
    public function prependMiddleware(string $middleware): static
    {
        if (array_search($middleware, $this->middleware) === false) {
            array_unshift($this->middleware, $middleware);
        }

        return $this;
    }

    /**
     * Add global middleware to the end of the stack.
     *
     * @param  string  $middleware
     * @return $this
     */
    public function pushMiddleware(string $middleware): static
    {
        if (array_search($middleware, $this->middleware) === false) {
            $this->middleware[] = $middleware;
        }

        return $this;
    }

    /**
     * Add middleware to the start of a group.
     *
     * @param  string  $group
     * @param  string  $middleware
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function prependMiddlewareToGroup(string $group, string $middleware): static
    {
        if (! isset($this->middlewareGroups[$group])) {
            throw new InvalidArgumentException("The [{$group}] middleware group has not been defined.");
        }

        if (array_search($middleware, $this->middlewareGroups[$group]) === false) {
            array_unshift($this->middlewareGroups[$group], $middleware);
        }

        $this->syncMiddlewareToRouter();

        return $this;
    }

    /**
     * Add middleware to the end of a group.
     *
     * @param  string  $group
     * @param  string  $middleware
     * @return $this
     *
     * @throws \InvalidArgumentException
     */
    public function appendMiddlewareToGroup(string $group, string $middleware): static
    {
        if (! isset($this->middlewareGroups[$group])) {
            throw new InvalidArgumentException("The [{$group}] middleware group has not been defined.");
        }

        if (array_search($middleware, $this->middlewareGroups[$group]) === false) {
            $this->middlewareGroups[$group][] = $middleware;
        }

        $this->syncMiddlewareToRouter();

        return $this;
    }

    /**
     * Put middleware first in the priority list.
     *
     * @param  string  $middleware
     * @return $this
     */
    public function prependToMiddlewarePriority(string $middleware): static
    {
        if (! in_array($middleware, $this->middlewarePriority)) {
            array_unshift($this->middlewarePriority, $middleware);
        }

        $this->syncMiddlewareToRouter();

        return $this;
    }

    /**
     * Put middleware last in the priority list.
     *
     * @param  string  $middleware
     * @return $this
     */
    public function appendToMiddlewarePriority(string $middleware): static
    {
        if (! in_array($middleware, $this->middlewarePriority)) {
            $this->middlewarePriority[] = $middleware;
        }

        $this->syncMiddlewareToRouter();

        return $this;
    }

    /**
     * Put middleware in the priority list before the first of $before.
     *
     * @param  string|list<string>  $before
     * @param  string  $middleware
     * @return $this
     */
    public function addToMiddlewarePriorityBefore(string|array $before, string $middleware): static
    {
        return $this->addToMiddlewarePriorityRelative($before, $middleware, after: false);
    }

    /**
     * Put middleware in the priority list after the last of $after.
     *
     * @param  string|list<string>  $after
     * @param  string  $middleware
     * @return $this
     */
    public function addToMiddlewarePriorityAfter(string|array $after, string $middleware): static
    {
        return $this->addToMiddlewarePriorityRelative($after, $middleware);
    }

    /**
     * Put middleware in the priority list relative to existing middleware.
     *
     * @param  string|list<string>  $existing
     * @param  string  $middleware
     * @param  bool  $after
     * @return $this
     */
    protected function addToMiddlewarePriorityRelative(string|array $existing, string $middleware, bool $after = true): static
    {
        if (! in_array($middleware, $this->middlewarePriority)) {
            $index = null;

            foreach ((array) $existing as $existing_middleware) {
                if (in_array($existing_middleware, $this->middlewarePriority)) {
                    $middleware_index = array_search($existing_middleware, $this->middlewarePriority);

                    if ($after && (is_null($index) || $middleware_index >= $index)) {
                        $index = $middleware_index + 1;
                    } elseif ($after === false && (is_null($index) || $middleware_index < $index)) {
                        $index = $middleware_index;
                    }
                }
            }

            if (is_null($index) || $index === count($this->middlewarePriority)) {
                $this->middlewarePriority[] = $middleware;
            } else {
                array_splice($this->middlewarePriority, $index, 0, $middleware);
            }
        }

        $this->syncMiddlewareToRouter();

        return $this;
    }

    /**
     * Give the router the groups, aliases and priority.
     *
     * @return void
     */
    protected function syncMiddlewareToRouter(): void
    {
        $this->router->middlewarePriority = $this->middlewarePriority;

        foreach ($this->middlewareGroups as $key => $middleware) {
            $this->router->middlewareGroup($key, $middleware);
        }

        foreach ($this->middlewareAliases as $key => $middleware) {
            $this->router->aliasMiddleware($key, $middleware);
        }
    }

    /**
     * The global middleware.
     *
     * @return list<string>
     */
    public function getGlobalMiddleware(): array
    {
        return $this->middleware;
    }

    /**
     * The route middleware groups.
     *
     * @return array<string, list<string>>
     */
    public function getMiddlewareGroups(): array
    {
        return $this->middlewareGroups;
    }

    /**
     * The middleware aliases.
     *
     * @return array<string, string>
     */
    public function getMiddlewareAliases(): array
    {
        return $this->middlewareAliases;
    }

    /**
     * The middleware priority.
     *
     * @return list<string>
     */
    public function getMiddlewarePriority(): array
    {
        return $this->middlewarePriority;
    }

    /**
     * The application the kernel handles requests for.
     *
     * @return \Voyager\Contracts\Core\FrameworkCore
     */
    public function getApplication(): FrameworkCore
    {
        return $this->app;
    }
}
