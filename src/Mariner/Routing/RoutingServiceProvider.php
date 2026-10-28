<?php

namespace Mariner\Routing;

use Mariner\Http\Request;
use Voyager\Contracts\Vessel\DataBindingException;
use Mariner\Contracts\Routing\ResponseFactory as ResponseFactoryContract;
use Mariner\Contracts\View\Factory as ViewFactoryContract;
use Mariner\Routing\Console\RouteCacheCommand;
use Mariner\Routing\Console\RouteClearCommand;
use Mariner\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Mariner\Routing\Contracts\ControllerDispatcher as ControllerDispatcherContract;
use Voyager\NutsAndBolts\DataObjects\Stringable;
use Voyager\NutsAndBolts\ServiceProvider;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Response;
use Mariner\Routing\Console\ControllerMakeCommand;
use Mariner\Routing\Console\MiddlewareMakeCommand;

class RoutingServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerRouter();
        $this->registerUrlGenerator();
        $this->registerRedirector();
        $this->registerPsrRequest();
        $this->registerPsrResponse();
        $this->registerResponseFactory();
        $this->registerCallableDispatcher();
        $this->registerControllerDispatcher();

        $this->app->registerSingleton(RouteCache::class);

        // Uri::route()/to()/action() build through the current request's URL generator, and
        // strings convert to the web Uri, which can be returned from a controller as a redirect.
        Uri::setUrlGeneratorResolver(fn () => $this->app->make('url'));

        Stringable::macro('toUri', function () {
            // Macroable binds the closure to the Stringable it's called on, so $this is that string.
            return Uri::of($this->value());
        });

        // Computer resolves commands through the container, so each one is bound before it's listed.
        $this->app->registerSingleton(RouteCacheCommand::class, fn ($app) => new RouteCacheCommand($app['files']));
        $this->app->registerSingleton(RouteClearCommand::class);
        $this->app->registerSingleton(ControllerMakeCommand::class);
        $this->app->registerSingleton(MiddlewareMakeCommand::class);

        $this->commands([RouteCacheCommand::class, RouteClearCommand::class, ControllerMakeCommand::class, MiddlewareMakeCommand::class]);
    }

    /**
     * Run from the compiled routes when route:cache has written them, otherwise finish the routes
     * the route files registered.
     *
     * Runs after every provider has booted, so a cache replaces whatever the route files would have
     * registered; LoadsWebResources::loadRoutesFrom() skips those files while the cache exists.
     *
     * @return void
     */
    public function boot()
    {
        $this->registerRequestSignatureValidation();

        $this->app->booted(function ($app) {
            $cache = $app->make(RouteCache::class);

            if ($cache->isCached()) {
                $cache->load();

                return;
            }

            $this->loadApplicationRoutes($app);

            // Routes name themselves after they join the collection (->name(), ->uses()), so the
            // lookups route() and action() rely on are rebuilt once every route file has run.
            $routes = $app['router']->getRoutes();
            $routes->refreshNameLookups();
            $routes->refreshActionLookups();
        });
    }

    /**
     * Load the app's route files named in config/routing.php: 'web' in the web middleware group,
     * 'api' in the api group under 'api_prefix'. A key set to null, or naming no file, loads nothing.
     *
     * @param  \Voyager\Contracts\Core\FrameworkCore  $app
     * @return void
     */
    protected function loadApplicationRoutes($app): void
    {
        $config = $app['config'];

        if (is_string($web = $config->get('routing.web')) && is_file($web)) {
            $app['router']->middleware('web')->group($web);
        }

        if (is_string($api = $config->get('routing.api')) && is_file($api)) {
            $app['router']->prefix($config->get('routing.api_prefix', 'api'))->middleware('api')->group($api);
        }
    }

    /**
     * Register the router instance.
     *
     * @return void
     */
    protected function registerRouter()
    {
        $this->app->scoped(RouteState::class, fn () => new RouteState);

        // The matched route, per request fiber; Router::findRoute() registers it.
        $this->app->scoped(Route::class, fn ($app) => $app->make(RouteState::class)->current);

        $this->app->registerSingleton('router', function ($app) {
            return new Router($app['signals'], $app);
        });
    }

    /**
     * Register the URL generator service.
     *
     * Scoped: each request (and each concurrent request fiber) gets a generator built from its own
     * request, so URLs, defaults and the forced root of one request never leak into another.
     *
     * @return void
     */
    protected function registerUrlGenerator()
    {
        $this->app->scoped('url', function ($app) {
            $url = new UrlGenerator(
                $app['router']->getRoutes(), $app['request'], $app['config']['webapp.asset_url']
            );

            // These resolvers give the generator the session (for previous URLs) and the
            // application keys (for signed URLs), read when they are needed.
            $url->setSessionResolver(function () use ($app) {
                return $app->isBound('session') ? $app['session'] : null;
            });

            $url->setKeyResolver(function () use ($app) {
                $config = $app->make('config');

                return [$config->get('app.key'), ...($config->get('app.previous_keys') ?? [])];
            });

            return $url;
        });
    }

    /**
     * Register the Redirector service.
     *
     * @return void
     */
    protected function registerRedirector()
    {
        $this->app->scoped('redirect', function ($app) {
            $redirector = new Redirector($app['url']);

            // If the session is set on the application instance, we'll inject it into
            // the redirector instance. This allows the redirect responses to allow
            // for the quite convenient "with" methods that flash to the session.
            if ($app->isBound('session.store')) {
                $redirector->setSession($app['session.store']);
            }

            return $redirector;
        });
    }

    /**
     * Register a binding for the PSR-7 request implementation.
     *
     * @return void
     *
     * @throws \Voyager\Contracts\Vessel\DataBindingException
     */
    protected function registerPsrRequest()
    {
        $this->app->bind(ServerRequestInterface::class, function ($app) {
            if (class_exists(PsrHttpFactory::class)) {
                $illuminateRequest = $app->make('request');
                $request = (new PsrHttpFactory)->createRequest($illuminateRequest);

                if ($illuminateRequest->getContentTypeFormat() !== 'json' && $illuminateRequest->request->count() === 0) {
                    return $request;
                }

                return $request->withParsedBody(
                    array_merge($request->getParsedBody() ?? [], $illuminateRequest->getPayload()->all())
                );
            }

            throw new DataBindingException('Unable to resolve PSR request. Please install the "symfony/psr-http-message-bridge" package.');
        });
    }

    /**
     * Register a binding for the PSR-7 response implementation.
     *
     * @return void
     *
     * @throws \Voyager\Contracts\Vessel\DataBindingException
     */
    protected function registerPsrResponse()
    {
        $this->app->bind(ResponseInterface::class, function () {
            if (class_exists(PsrHttpFactory::class)) {
                return (new PsrHttpFactory)->createResponse(new Response);
            }

            throw new DataBindingException('Unable to resolve PSR response. Please install the "symfony/psr-http-message-bridge" package.');
        });
    }

    /**
     * Register the response factory implementation.
     *
     * @return void
     */
    protected function registerResponseFactory()
    {
        $this->app->scoped(ResponseFactoryContract::class, function ($app) {
            return new ResponseFactory($app[ViewFactoryContract::class], $app['redirect']);
        });
    }

    /**
     * Register the callable dispatcher.
     *
     * @return void
     */
    protected function registerCallableDispatcher()
    {
        $this->app->registerSingleton(CallableDispatcherContract::class, function ($app) {
            return new CallableDispatcher($app);
        });
    }

    /**
     * Register the controller dispatcher.
     *
     * @return void
     */
    protected function registerControllerDispatcher()
    {
        $this->app->registerSingleton(ControllerDispatcherContract::class, function ($app) {
            return new ControllerDispatcher($app);
        });
    }

    /**
     * Register the signed-URL macros on the request.
     */
    protected function registerRequestSignatureValidation(): void
    {
        $app = $this->app;

        Request::macro('hasValidSignature', function ($absolute = true) use ($app) {
            return $app->make('url')->hasValidSignature($this, $absolute);
        });

        Request::macro('hasValidRelativeSignature', function () use ($app) {
            return $app->make('url')->hasValidSignature($this, false);
        });

        Request::macro('hasValidSignatureWhileIgnoring', function ($ignoreQuery = [], $absolute = true) use ($app) {
            return $app->make('url')->hasValidSignature($this, $absolute, $ignoreQuery);
        });

        Request::macro('hasValidRelativeSignatureWhileIgnoring', function ($ignoreQuery = []) use ($app) {
            return $app->make('url')->hasValidSignature($this, false, $ignoreQuery);
        });
    }
}
