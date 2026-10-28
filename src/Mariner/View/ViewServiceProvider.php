<?php

namespace Mariner\View;

use Mariner\View\Console\ViewCacheCommand;
use Mariner\View\Console\ViewClearCommand;
use Voyager\Vessel\ControlPanel;
use Voyager\NutsAndBolts\ServiceProvider;
use Mariner\Http\Events\RequestHandled;
use Mariner\Routing\RouteState;
use Mariner\View\Compilers\BladeCompiler;
use Mariner\View\Engines\CompilerEngine;
use Mariner\View\Engines\EngineResolver;
use Mariner\View\Engines\FileEngine;
use Mariner\View\Engines\PhpEngine;

class ViewServiceProvider extends ServiceProvider
{
    /**
     * Register the service provider.
     *
     * @return void
     */
    public function register()
    {
        $this->registerFactory();
        $this->registerViewFinder();
        $this->registerBladeCompiler();
        $this->registerEngineResolver();

        $this->app->registerSingleton(ViewCacheCommand::class);
        $this->app->registerSingleton(ViewClearCommand::class, fn ($app) => new ViewClearCommand($app['files']));
        $this->commands([ViewCacheCommand::class, ViewClearCommand::class]);
    }

    /**
     * Register the view environment.
     *
     * @return void
     */
    public function registerFactory()
    {
        $this->app->scoped(RenderState::class, fn () => new RenderState);

        $this->app->scoped(ViteState::class, fn () => new ViteState);

        // One Vite, so configuration set at boot (Vite::useBuildDirectory(), useCspNonce() in
        // middleware, prefetching) holds for every render; nonces and preloads are per response.
        $this->app->registerSingleton(Vite::class, function ($app) {
            $vite = new Vite;
            $vite->useViteState(fn () => $app->make(ViteState::class));

            return $vite;
        });

        $this->app->registerSingleton('view', function ($app) {
            // Next we need to grab the engine resolver instance that will be used by the
            // environment. The resolver will be used by an environment to get each of
            // the various engine implementations such as plain PHP or Blade engine.
            $resolver = $app['view.engine.resolver'];

            $finder = $app['view.finder'];

            $factory = $this->createFactory($resolver, $finder, $app['signals']);

            // We will also set the container instance on this view environment since the
            // view composers may be classes registered in the container, which allows
            // for great testable, flexible composers for the application developer.
            $factory->setContainer($app);

            // Engines, finder, composers and boot-time shares are configuration; what a request
            // renders (sections, stacks, slots, and data shared while handling it) is its own.
            $factory->useRenderState(
                fn () => $app->make(RenderState::class),
                fn () => $app->make(RouteState::class)->request !== null,
            );

            $factory->share('app', $app);

            return $factory;
        });
    }

    /**
     * Create a new Factory Instance.
     *
     * @param  \Mariner\View\Engines\EngineResolver  $resolver
     * @param  \Mariner\View\ViewFinderInterface  $finder
     * @param  \Voyager\Contracts\Signals\SignalDispatcher  $events
     * @return \Mariner\View\Factory
     */
    protected function createFactory($resolver, $finder, $events)
    {
        return new Factory($resolver, $finder, $events);
    }

    /**
     * Register the view finder implementation.
     *
     * @return void
     */
    public function registerViewFinder()
    {
        $this->app->bind('view.finder', function ($app) {
            return new FileViewFinder($app['files'], $app['config']['view.paths']);
        });
    }

    /**
     * Register the Blade compiler implementation.
     *
     * @return void
     */
    public function registerBladeCompiler()
    {
        $this->app->registerSingleton('blade.compiler', function ($app) {
            return tap(new BladeCompiler(
                $app['files'],
                $app['config']['view.compiled'],
                $app['config']->get('view.relative_hash', false) ? $app->basePath() : '',
                $app['config']->get('view.cache', true),
                $app['config']->get('view.compiled_extension', 'php'),
                $app['config']->get('view.check_cache_timestamps', true),
            ), function ($blade) {
                $blade->component('dynamic-component', DynamicComponent::class);
            });
        });
    }

    /**
     * Register the engine resolver instance.
     *
     * @return void
     */
    public function registerEngineResolver()
    {
        $this->app->registerSingleton('view.engine.resolver', function () {
            $resolver = new EngineResolver;

            // Next, we will register the various view engines with the resolver so that the
            // environment will resolve the engines needed for various views based on the
            // extension of view file. We call a method for each of the view's engines.
            foreach (['file', 'php', 'blade'] as $engine) {
                $this->{'register'.ucfirst($engine).'Engine'}($resolver);
            }

            return $resolver;
        });
    }

    /**
     * Register the file engine implementation.
     *
     * @param  \Mariner\View\Engines\EngineResolver  $resolver
     * @return void
     */
    public function registerFileEngine($resolver)
    {
        $resolver->register('file', function () {
            return new FileEngine(ControlPanel::getInstance()->make('files'));
        });
    }

    /**
     * Register the PHP engine implementation.
     *
     * @param  \Mariner\View\Engines\EngineResolver  $resolver
     * @return void
     */
    public function registerPhpEngine($resolver)
    {
        $resolver->register('php', function () {
            return new PhpEngine(ControlPanel::getInstance()->make('files'));
        });
    }

    /**
     * Register the Blade engine implementation.
     *
     * @param  \Mariner\View\Engines\EngineResolver  $resolver
     * @return void
     */
    public function registerBladeEngine($resolver)
    {
        $resolver->register('blade', function () {
            $app = ControlPanel::getInstance();

            $compiler = new CompilerEngine(
                $app->make('blade.compiler'),
                $app->make('files'),
            );

            // A long-running server serves many requests from one compiler: check view freshness
            // again on the next request instead of trusting this request's checks forever.
            $app['signals']->listen(RequestHandled::class, static function () use ($compiler) {
                $compiler->forgetCompiledOrNotExpired();
            });

            return $compiler;
        });
    }

    /**
     * Bootstrap the view services.
     *
     * @return void
     */
    public function boot()
    {
        // Requests that suspend mid-render keep their own output buffers.
        if ($this->app->isBound('event-loop')) {
            FiberOutputBuffers::register($this->app->make('event-loop'));
        }
    }
}
