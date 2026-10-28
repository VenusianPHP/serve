<?php

namespace Mariner\Core\Providers;

use Mariner\Auth\AuthServiceProvider;
use Mariner\Auth\Passwords\PasswordResetServiceProvider;
use Mariner\Contracts\Http\ExceptionRenderer as ExceptionRendererContract;
use Mariner\Cookie\CookieServiceProvider;
use Mariner\Core\Console\ServeCommand;
use Mariner\Core\Console\ServeWorkerCommand;
use Mariner\Core\Console\WebInstallCommand;
use Mariner\Core\Console\DownCommand;
use Mariner\Core\Console\UpCommand;
use Mariner\Core\Http\ExceptionRenderer;
use Mariner\Testing\LoggedExceptionCollection;
use Voyager\Log\Signals\MessageLogged;
use Mariner\Core\Http\Kernel;
use Mariner\Core\Server\HttpServer;
use Mariner\Core\Server\ServerOptions;
use Mariner\Http\HttpServiceProvider;
use Mariner\Image\ImageServiceProvider;
use Mariner\Mail\MailServiceProvider;
use Mariner\Notifications\NotificationServiceProvider;
use Mariner\NutsAndBolts\Facades\Facade;
use Mariner\NutsAndBolts\StringMacros;
use Mariner\Routing\RoutingServiceProvider;
use Mariner\Session\SessionServiceProvider;
use Mariner\Translation\TranslationServiceProvider;
use Mariner\Validation\FormRequestServiceProvider;
use Mariner\Validation\ValidationServiceProvider;
use Mariner\View\ViewServiceProvider;
use Voyager\NutsAndBolts\AggregateServiceProvider;

class MarinerServiceProvider extends AggregateServiceProvider
{
    /**
     * The web services' container keys, and the classes and contracts that resolve to each.
     *
     * @var array<string, list<class-string>>
     */
    public const ALIASES = [
        'auth' => [\Mariner\Auth\AuthManager::class, \Mariner\Contracts\Auth\Factory::class],
        'auth.driver' => [\Mariner\Contracts\Auth\Guard::class],
        'auth.password' => [\Mariner\Auth\Passwords\PasswordBrokerManager::class, \Mariner\Contracts\Auth\PasswordBrokerFactory::class],
        'auth.password.broker' => [\Mariner\Auth\Passwords\PasswordBroker::class, \Mariner\Contracts\Auth\PasswordBroker::class],
        'blade.compiler' => [\Mariner\View\Compilers\BladeCompiler::class],
        'cookie' => [\Mariner\Cookie\CookieJar::class, \Mariner\Contracts\Cookie\Factory::class, \Mariner\Contracts\Cookie\QueueingFactory::class],
        'image' => [\Mariner\Image\ImageManager::class],
        'mail.manager' => [\Mariner\Mail\MailManager::class, \Mariner\Contracts\Mail\Factory::class],
        'mailer' => [\Mariner\Mail\Mailer::class, \Mariner\Contracts\Mail\Mailer::class, \Mariner\Contracts\Mail\MailQueue::class],
        'redirect' => [\Mariner\Routing\Redirector::class],
        'request' => [\Mariner\Http\Request::class, \Symfony\Component\HttpFoundation\Request::class],
        'router' => [\Mariner\Routing\Router::class, \Mariner\Contracts\Routing\Registrar::class, \Mariner\Contracts\Routing\BindingRegistrar::class],
        'session' => [\Mariner\Session\SessionManager::class],
        'session.store' => [\Mariner\Session\Store::class, \Mariner\Contracts\Session\Session::class],
        'translator' => [\Mariner\Translation\Translator::class, \Mariner\Contracts\Translation\Translator::class],
        'url' => [\Mariner\Routing\UrlGenerator::class, \Mariner\Contracts\Routing\UrlGenerator::class],
        'validator' => [\Mariner\Validation\Factory::class, \Mariner\Contracts\Validation\Factory::class],
        'view' => [\Mariner\View\Factory::class, \Mariner\Contracts\View\Factory::class],
    ];

    protected array $providers = [
        HttpServiceProvider::class,
        CookieServiceProvider::class,
        SessionServiceProvider::class,
        RoutingServiceProvider::class,
        AuthServiceProvider::class,
        PasswordResetServiceProvider::class,
        TranslationServiceProvider::class,
        ValidationServiceProvider::class,
        FormRequestServiceProvider::class,
        ViewServiceProvider::class,
        MailServiceProvider::class,
        NotificationServiceProvider::class,
        ImageServiceProvider::class,
    ];

    /**
     * The package's config files, keyed by the config key each one fills.
     *
     * @return array<string, string>
     */
    protected function configs(): array
    {
        $dir = dirname(__DIR__, 4).'/config';

        return [
            'auth' => "{$dir}/auth.php",
            'cors' => "{$dir}/cors.php",
            'images' => "{$dir}/images.php",
            'mail' => "{$dir}/mail.php",
            'routing' => "{$dir}/routing.php",
            'serve' => "{$dir}/serve.php",
            'services' => "{$dir}/services.php",
            'session' => "{$dir}/session.php",
            'view' => "{$dir}/view.php",
            'webapp' => "{$dir}/webapp.php",
        ];
    }

    public function register(): void
    {
        foreach ($this->configs() as $key => $path) {
            $this->mergeConfigFrom($path, $key);
        }

        $this->registerWebPaths();

        $this->registerContainerAliases();

        // App-wide: the renderer holds the app's render callbacks, never a request.
        $this->app->registerSingleton(ExceptionRenderer::class);
        $this->app->alias(ExceptionRenderer::class, ExceptionRendererContract::class);

        // One kernel for every request: front controller and server alike.
        $this->app->registerSingleton(Kernel::class);

        // A new server each time, on this app: `serve` makes one, and so may a running sketch, to
        // attach to its own loop. make(HttpServer::class, ['options' => ...]) overrides config/serve.php.
        $this->app->bind(HttpServer::class, fn ($app, array $parameters = []) => new HttpServer(
            $app, $parameters['options'] ?? ServerOptions::fromConfig((array) $app['config']->get('serve', [])),
        ));

        // Computer resolves commands through the container, so each one is bound before it's listed.
        $this->app->registerSingleton(WebInstallCommand::class, fn ($app) => new WebInstallCommand($app['files']));
        $this->app->registerSingleton(DownCommand::class);
        $this->app->registerSingleton(UpCommand::class);
        $this->app->registerSingleton(ServeCommand::class);
        $this->app->registerSingleton(ServeWorkerCommand::class);
        $this->commands([WebInstallCommand::class, DownCommand::class, UpCommand::class, ServeCommand::class, ServeWorkerCommand::class]);

        Facade::setFacadeApplication($this->app);

        StringMacros::register();

        parent::register();
    }

    public function boot(): void
    {
        $published = [];
        foreach ($this->configs() as $key => $path) {
            $published[$path] = $this->app->configPath("{$key}.php");
        }

        $this->publishes($published, 'mariner-config');

        $this->registerExceptionTracking();
    }

    /**
     * Under unit tests, collect every exception the app logs, for TestResponse's failure output.
     */
    protected function registerExceptionTracking(): void
    {
        if (! $this->app->runningUnitTests()) {
            return;
        }

        $this->app->registerInstance(LoggedExceptionCollection::class, new LoggedExceptionCollection);

        $this->app->make('signals')->listen(MessageLogged::class, function (MessageLogged $event) {
            if (isset($event->context['exception'])) {
                $this->app->make(LoggedExceptionCollection::class)->push($event->context['exception']);
            }
        });
    }

    /**
     * Bind the web directories Venusian's core doesn't know about, unless the application already has.
     */
    protected function registerWebPaths(): void
    {
        foreach (['public' => 'public', 'resources' => 'resources', 'lang' => 'lang'] as $name => $directory) {
            if (! $this->app->isBound("path.{$name}")) {
                $this->app->registerInstance("path.{$name}", $this->app->basePath($directory));
            }
        }
    }

    /**
     * Let classes and contracts resolve the web services registered under string keys, so
     * helpers, type-hints and facades can ask for either.
     */
    protected function registerContainerAliases(): void
    {
        foreach (self::ALIASES as $abstract => $aliases) {
            foreach ($aliases as $alias) {
                $this->app->alias($abstract, $alias);
            }
        }
    }
}
