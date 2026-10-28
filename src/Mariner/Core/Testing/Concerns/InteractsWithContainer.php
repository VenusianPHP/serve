<?php

namespace Mariner\Core\Testing\Concerns;

use Closure;
use Mariner\View\Vite;
use Voyager\NutsAndBolts\Defer\DeferredCallbackCollection;
use Mariner\NutsAndBolts\Facades\Vite as ViteFacade;
use Voyager\NutsAndBolts\HtmlString;
use Mockery;

trait InteractsWithContainer
{
    /**
     * The original Vite handler.
     *
     * @var \Mariner\View\Vite|null
     */
    protected $originalVite;


    /**
     * The original deferred callbacks collection.
     *
     * @var \Voyager\NutsAndBolts\Defer\DeferredCallbackCollection|null
     */
    protected $originalDeferredCallbacksCollection;

    /**
     * What each swapped abstract resolved to before the swap; null when it was unbound.
     *
     * @var array<string, mixed>
     */
    protected array $replacedInstances = [];

    /**
     * Register an instance of an object in the container.
     *
     * @template TSwap of object
     *
     * @param  string  $abstract
     * @param  TSwap  $instance
     * @return TSwap
     */
    protected function swap($abstract, $instance)
    {
        return $this->instance($abstract, $instance);
    }

    /**
     * Register an instance of an object in the container.
     *
     * @template TInstance of object
     *
     * @param  string  $abstract
     * @param  TInstance  $instance
     * @return TInstance
     */
    protected function instance($abstract, $instance)
    {
        // Vessel cannot forget an instance alone, so keep what the abstract resolved to for forgetMock().
        if (! array_key_exists($abstract, $this->replacedInstances)) {
            $this->replacedInstances[$abstract] = $this->app->isBound($abstract) ? $this->app->make($abstract) : null;
        }

        $this->app->registerInstance($abstract, $instance);

        return $instance;
    }

    /**
     * Mock an instance of an object in the container.
     *
     * @template TInstance of object
     *
     * @param  string|class-string<TInstance>  $abstract
     * @param  \Closure|null  $mock
     * @return ($abstract is class-string<TInstance> ? TInstance&\Mockery\MockInterface : \Mockery\MockInterface)
     */
    protected function mock($abstract, ?Closure $mock = null)
    {
        return $this->instance($abstract, Mockery::mock(...array_filter(func_get_args())));
    }

    /**
     * Mock a partial instance of an object in the container.
     *
     * @template TInstance of object
     *
     * @param  string|class-string<TInstance>  $abstract
     * @param  \Closure|null  $mock
     * @return ($abstract is class-string<TInstance> ? TInstance&\Mockery\MockInterface : \Mockery\MockInterface)
     */
    protected function partialMock($abstract, ?Closure $mock = null)
    {
        return $this->instance($abstract, Mockery::mock(...array_filter(func_get_args()))->makePartial());
    }

    /**
     * Spy an instance of an object in the container.
     *
     * @template TInstance of object
     *
     * @param  string|class-string<TInstance>  $abstract
     * @param  \Closure|null  $mock
     * @return ($abstract is class-string<TInstance> ? TInstance&\Mockery\MockInterface : \Mockery\MockInterface)
     */
    protected function spy($abstract, ?Closure $mock = null)
    {
        return $this->instance($abstract, Mockery::spy(...array_filter(func_get_args())));
    }

    /**
     * Instruct the container to forget a previously mocked / spied instance of an object.
     *
     * @param  string  $abstract
     * @return $this
     */
    protected function forgetMock($abstract)
    {
        $original = $this->replacedInstances[$abstract] ?? null;
        unset($this->replacedInstances[$abstract]);

        is_null($original) ? $this->app->offsetUnset($abstract) : $this->app->registerInstance($abstract, $original);

        return $this;
    }

    /**
     * Register an empty handler for Vite in the container.
     *
     * @return $this
     */
    protected function withoutVite()
    {
        if ($this->originalVite == null) {
            $this->originalVite = app(Vite::class);
        }

        ViteFacade::clearResolvedInstance();

        $this->swap(Vite::class, new class extends Vite
        {
            public function __invoke($entrypoints, $buildDirectory = null)
            {
                return new HtmlString('');
            }

            public function __call($method, $parameters): mixed
            {
                return '';
            }

            public function __toString()
            {
                return '';
            }

            public function useIntegrityKey($key)
            {
                return $this;
            }

            public function useBuildDirectory($path)
            {
                return $this;
            }

            public function useHotFile($path)
            {
                return $this;
            }

            public function withEntryPoints($entryPoints)
            {
                return $this;
            }

            public function useScriptTagAttributes($attributes)
            {
                return $this;
            }

            public function useStyleTagAttributes($attributes)
            {
                return $this;
            }

            public function usePreloadTagAttributes($attributes)
            {
                return $this;
            }

            public function preloadedAssets()
            {
                return [];
            }

            public function reactRefresh(): HtmlString
            {
                return new HtmlString('');
            }

            public function content($asset, $buildDirectory = null)
            {
                return '';
            }

            public function asset($asset, $buildDirectory = null)
            {
                return '';
            }
        });

        return $this;
    }

    /**
     * Restore Vite in the container.
     *
     * @return $this
     */
    protected function withVite()
    {
        if ($this->originalVite) {
            $this->app->registerInstance(Vite::class, $this->originalVite);
        }

        return $this;
    }



    /**
     * Execute deferred functions immediately.
     *
     * @return $this
     */
    protected function withoutDefer()
    {
        if ($this->originalDeferredCallbacksCollection == null) {
            $this->originalDeferredCallbacksCollection = $this->app->make(DeferredCallbackCollection::class);
        }

        $this->swap(DeferredCallbackCollection::class, new class extends DeferredCallbackCollection
        {
            public function offsetSet(mixed $offset, mixed $value): void
            {
                $value();
            }
        });

        return $this;
    }

    /**
     * Restore deferred functions.
     *
     * @return $this
     */
    protected function withDefer()
    {
        if ($this->originalDeferredCallbacksCollection) {
            $this->app->registerInstance(DeferredCallbackCollection::class, $this->originalDeferredCallbacksCollection);
        }

        return $this;
    }
}
