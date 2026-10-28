<?php

namespace Mariner\Session;

use Voyager\Contracts\Cache\Factory as CacheFactory;
use Mariner\Session\Middleware\StartSession;
use Voyager\NutsAndBolts\ServiceProvider;
use Mariner\Session\Console\SessionTableCommand;

class SessionServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Computer resolves commands through the container, so each one is bound before it's listed.
        $this->app->registerSingleton(SessionTableCommand::class);
        $this->commands([SessionTableCommand::class]);

        $this->registerSessionManager();

        $this->registerSessionDriver();

        $this->app->registerSingleton(StartSession::class, function ($app) {
            return new StartSession($app->make(SessionManager::class), function () use ($app) {
                return $app->make(CacheFactory::class);
            });
        });
    }

    /**
     * Register the session manager instance.
     *
     * @return void
     */
    protected function registerSessionManager()
    {
        $this->app->registerSingleton('session', function ($app) {
            return new SessionManager($app);
        });
    }

    /**
     * Register the session driver instance.
     *
     * @return void
     */
    protected function registerSessionDriver()
    {
        $this->app->scoped(SessionState::class, fn () => new SessionState);

        $this->app->scoped('session.store', function ($app) {
            // First, we will create the session manager which is responsible for the
            // creation of the various session drivers when they are needed by the
            // application instance, and will resolve them on a lazy load basis.
            return $app->make('session')->driver();
        });
    }
}
