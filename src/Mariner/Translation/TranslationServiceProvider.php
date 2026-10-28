<?php

namespace Mariner\Translation;

use Voyager\Contracts\NutsAndBolts\DeferrableProvider;
use Voyager\NutsAndBolts\ServiceProvider;

class TranslationServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerLoader();

        $this->app->scoped(LocaleState::class, fn () => new LocaleState);

        $this->app->registerSingleton('translator', function ($app) {
            $trans = new Translator($app['translation.loader'], $app['config']->get('webapp.locale', 'en'));

            $trans->setFallback($app['config']->get('webapp.fallback_locale', 'en'));

            // The configured locale is the default; App::setLocale() and Localizable change only
            // the current request's locale.
            $trans->useLocaleState(fn () => $app->make(LocaleState::class));

            return $trans;
        });
    }

    /**
     * Register the translation line loader.
     *
     * @return void
     */
    protected function registerLoader()
    {
        $this->app->registerSingleton('translation.loader', function ($app) {
            return new FileLoader($app['files'], [__DIR__.'/lang', $app['path.lang']]);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return ['translator', 'translation.loader'];
    }
}
