<?php

namespace Mariner\Notifications;

use Mariner\Contracts\Notifications\Dispatcher as DispatcherContract;
use Mariner\Contracts\Notifications\Factory as FactoryContract;
use Mariner\NutsAndBolts\Concerns\LoadsWebResources;
use Voyager\NutsAndBolts\ServiceProvider;
use Mariner\Notifications\Console\NotificationTableCommand;

class NotificationServiceProvider extends ServiceProvider
{
    use LoadsWebResources;

    /**
     * Boot the application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadViewsFrom(__DIR__.'/resources/views', 'notifications');

        $this->publishes([
            __DIR__.'/resources/views' => resource_path('views/vendor/notifications'),
        ], 'mariner-notifications');
    }

    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        // Computer resolves commands through the container, so each one is bound before it's listed.
        $this->app->registerSingleton(NotificationTableCommand::class);
        $this->commands([NotificationTableCommand::class]);

        $this->app->registerSingleton(ChannelManager::class, fn ($app) => new ChannelManager($app));

        $this->app->alias(
            ChannelManager::class, DispatcherContract::class
        );

        $this->app->alias(
            ChannelManager::class, FactoryContract::class
        );
    }
}
