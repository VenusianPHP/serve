<?php

namespace Mariner\Mail;

use Voyager\Contracts\NutsAndBolts\DeferrableProvider;
use Voyager\NutsAndBolts\ServiceProvider;

class MailServiceProvider extends ServiceProvider implements DeferrableProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerMarinerMailer();
        $this->registerMarkdownRenderer();
    }

    /**
     * Register the Mariner mailer instance.
     *
     * @return void
     */
    protected function registerMarinerMailer()
    {
        $this->app->registerSingleton('mail.manager', function ($app) {
            return new MailManager($app);
        });

        $this->app->bind('mailer', function ($app) {
            return $app->make('mail.manager')->mailer();
        });
    }

    /**
     * Register the Markdown renderer instance.
     *
     * @return void
     */
    protected function registerMarkdownRenderer()
    {
        $this->publishes([
            __DIR__.'/resources/views' => resource_path('views/vendor/mail'),
        ], 'mariner-mail');

        $this->app->registerSingleton(Markdown::class, function ($app) {
            $config = $app->make('config');

            return new Markdown($app->make('view'), [
                'theme' => $config->get('mail.markdown.theme', 'default'),
                'paths' => $config->get('mail.markdown.paths', []),
                'extensions' => $config->get('mail.markdown.extensions', []),
            ]);
        });
    }

    /**
     * Get the services provided by the provider.
     *
     * @return array
     */
    public function provides(): array
    {
        return [
            'mail.manager',
            'mailer',
            Markdown::class,
        ];
    }
}
