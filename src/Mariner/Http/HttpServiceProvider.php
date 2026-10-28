<?php

namespace Mariner\Http;

use Mariner\Contracts\Http\MaintenanceMode;
use Mariner\Http\Maintenance\MaintenanceModeManager;
use Mariner\Http\Resources\ResourceGuesser;
use Voyager\NutsAndBolts\Collection;
use Voyager\NutsAndBolts\ServiceProvider;
use Voyager\Pagination\AbstractCursorPaginator;
use Voyager\Pagination\AbstractPaginator;
use Voyager\Pagination\Cursor;

class HttpServiceProvider extends ServiceProvider
{
    /**
     * Register the request binding.
     *
     * Scoped, so each request fiber holds its own: the HTTP kernel registers the incoming request
     * with registerInstance(). Outside a request (Computer commands, sketches, tests) the default is
     * a GET for config('webapp.url'), so URLs generate against the configured root.
     *
     * @return void
     */
    public function register(): void
    {
        $this->app->registerSingleton(MaintenanceModeManager::class);
        // Resolved per use: a test or a deployment can switch webapp.maintenance.driver at runtime.
        $this->app->bind(MaintenanceMode::class, fn ($app) => $app->make(MaintenanceModeManager::class)->driver());

        $this->app->scoped('request', function ($app) {
            $uri = $app['config']->get('webapp.url', 'http://localhost');
            $server = $_SERVER;

            if ($path = parse_url($uri, PHP_URL_PATH)) {
                $server = array_merge($server, ['SCRIPT_FILENAME' => $path, 'SCRIPT_NAME' => $path]);
            }

            return Request::create($uri, 'GET', [], [], [], $server);
        });
    }

    /**
     * Teach Venusian's paginators to read the page, path, query string and cursor from the request.
     *
     * Every resolver reads the request when it is called, never when it is registered, so each
     * request (and each concurrent request fiber) paginates from its own input.
     *
     * @return void
     */
    public function boot(): void
    {
        // Laravel's Collection::toResourceCollection(), for collections of models using TransformsToResource.
        Collection::macro('toResourceCollection', function (?string $resourceClass = null) {
            return ResourceGuesser::forCollection($this, $resourceClass);
        });

        // And on paginators, given the paginator itself so the collection answers with its links and meta.
        foreach ([AbstractPaginator::class, AbstractCursorPaginator::class] as $paginator) {
            $paginator::macro('toResourceCollection', function (?string $resourceClass = null) {
                return ResourceGuesser::forCollection($this, $resourceClass);
            });
        }

        AbstractPaginator::currentPathResolver(fn () => $this->app['request']->url());

        AbstractPaginator::currentPageResolver(function ($pageName = 'page') {
            $page = $this->app['request']->input($pageName);

            if (filter_var($page, FILTER_VALIDATE_INT) !== false && (int) $page >= 1) {
                return (int) $page;
            }

            return 1;
        });

        AbstractPaginator::queryStringResolver(fn () => $this->app['request']->query());

        AbstractCursorPaginator::currentCursorResolver(
            fn ($cursorName = 'cursor') => Cursor::fromEncoded($this->app['request']->input($cursorName))
        );
    }
}
