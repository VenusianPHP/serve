<?php

namespace Mariner\Auth;

use Mariner\Auth\Access\Gate;
use Mariner\Auth\Middleware\RequirePassword;
use Mariner\Contracts\Auth\Access\Gate as GateContract;
use Mariner\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Mariner\Contracts\Routing\ResponseFactory;
use Mariner\Contracts\Routing\UrlGenerator;
use Voyager\NutsAndBolts\ServiceProvider;
use Mariner\Auth\Console\ClearResetsCommand;

class AuthServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Computer resolves commands through the container, so each one is bound before it's listed.
        $this->app->registerSingleton(ClearResetsCommand::class);
        $this->commands([ClearResetsCommand::class]);

        $this->registerAuthenticator();
        $this->registerUserResolver();
        $this->registerAccessGate();
        $this->registerRequirePassword();
    }

    /**
     * Register the authenticator services.
     *
     * @return void
     */
    protected function registerAuthenticator()
    {
        $this->app->registerSingleton('auth', fn ($app) => new AuthManager($app));

        $this->app->scoped(GuardState::class, fn () => new GuardState);

        $this->app->scoped('auth.driver', fn ($app) => $app['auth']->guard());
    }

    /**
     * Register a resolver for the authenticated user.
     *
     * @return void
     */
    protected function registerUserResolver()
    {
        $this->app->bind(AuthenticatableContract::class, fn ($app) => call_user_func($app['auth']->userResolver()));
    }

    /**
     * Register the access gate service.
     *
     * @return void
     */
    protected function registerAccessGate()
    {
        $this->app->registerSingleton(GateContract::class, function ($app) {
            return new Gate($app, fn () => call_user_func($app['auth']->userResolver()));
        });
    }

    /**
     * Register a resolver for the authenticated user.
     *
     * @return void
     */
    protected function registerRequirePassword()
    {
        $this->app->bind(RequirePassword::class, function ($app) {
            return new RequirePassword(
                $app[ResponseFactory::class],
                $app[UrlGenerator::class],
                $app['config']->get('auth.password_timeout')
            );
        });
    }
}
