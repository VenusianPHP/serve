<?php

namespace Mariner\Cookie;

use Voyager\NutsAndBolts\ServiceProvider;

class CookieServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->app->scoped(CookieQueue::class, fn () => new CookieQueue);

        $this->app->registerSingleton('cookie', function ($app) {
            $config = $app->make('config')->get('session');

            $jar = (new CookieJar)->setDefaultPathAndDomain(
                $config['path'], $config['domain'], $config['secure'], $config['same_site'] ?? null
            );

            // The jar's defaults are configuration; the cookies it queues belong to one request.
            $jar->useCookieQueue(fn () => $app->make(CookieQueue::class));

            return $jar;
        });
    }
}
