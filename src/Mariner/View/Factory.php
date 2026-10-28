<?php

namespace Mariner\View;

use Closure;
use Voyager\Contracts\Vessel\TheServiceContainer;
use Voyager\Contracts\Signals\SignalDispatcher;
use Voyager\Contracts\NutsAndBolts\Arrayable;
use Mariner\Contracts\View\Factory as FactoryContract;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\NutsAndBolts\Concerns\Macroable;
use Mariner\View\Engines\EngineResolver;
use InvalidArgumentException;

class Factory implements FactoryContract
{
    use Macroable,
        Concerns\ManagesComponents,
        Concerns\ManagesEvents,
        Concerns\ManagesFragments,
        Concerns\ManagesLayouts,
        Concerns\ManagesLoops,
        Concerns\ManagesStacks,
        Concerns\ManagesTranslations;

    /**
     * The engine implementation.
     *
     * @var \Mariner\View\Engines\EngineResolver
     */
    protected $engines;

    /**
     * The view finder implementation.
     *
     * @var \Mariner\View\ViewFinderInterface
     */
    protected $finder;

    /**
     * The event dispatcher instance.
     *
     * @var \Voyager\Contracts\Signals\SignalDispatcher
     */
    protected $events;

    /**
     * The IoC container instance.
     *
     * @var \Voyager\Contracts\Vessel\TheServiceContainer
     */
    protected $container;

    /**
     * Data that should be available to all templates.
     *
     * @var array
     */
    protected $shared = [];

    /**
     * Resolves the current request's render state, once a container has provided one.
     *
     * @var (\Closure(): \Mariner\View\RenderState)|null
     */
    protected ?Closure $renderStateResolver = null;

    /**
     * Tells whether a request is being handled right now.
     *
     * @var (\Closure(): bool)|null
     */
    protected ?Closure $handlingRequestResolver = null;

    /**
     * The render state used when no container provides one, as when the factory is built directly.
     */
    protected ?RenderState $localRenderState = null;

    /**
     * The extension to engine bindings.
     *
     * @var array
     */
    protected $extensions = [
        'blade.php' => 'blade',
        'php' => 'php',
        'css' => 'file',
        'html' => 'file',
    ];

    /**
     * The view composer events.
     *
     * @var array
     */
    protected $composers = [];



    /**
     * The cached array of engines for paths.
     *
     * @var array
     */
    protected $pathEngineCache = [];

    /**
     * The cache of normalized names for views.
     *
     * @var array
     */
    protected $normalizedNameCache = [];

    /**
     * Create a new view factory instance.
     *
     * @param  \Mariner\View\Engines\EngineResolver  $engines
     * @param  \Mariner\View\ViewFinderInterface  $finder
     * @param  \Voyager\Contracts\Signals\SignalDispatcher  $events
     */
    public function __construct(EngineResolver $engines, ViewFinderInterface $finder, SignalDispatcher $events)
    {
        $this->finder = $finder;
        $this->events = $events;
        $this->engines = $engines;

        $this->share('__env', $this);
    }

    /**
     * Get the evaluated view contents for the given view.
     *
     * @param  string  $path
     * @param  \Voyager\Contracts\NutsAndBolts\Arrayable|array  $data
     * @param  array  $mergeData
     * @return \Mariner\Contracts\View\View
     */
    public function file($path, $data = [], $mergeData = [])
    {
        $data = array_merge($mergeData, $this->parseData($data));

        return tap($this->viewInstance($path, $path, $data), function ($view) {
            $this->callCreator($view);
        });
    }

    /**
     * Get the evaluated view contents for the given view.
     *
     * @param  string  $view
     * @param  \Voyager\Contracts\NutsAndBolts\Arrayable|array  $data
     * @param  array  $mergeData
     * @return \Mariner\Contracts\View\View
     */
    public function make($view, $data = [], $mergeData = [])
    {
        $path = $this->finder->find(
            $view = $this->normalizeName($view)
        );

        // Next, we will create the view instance and call the view creator for the view
        // which can set any data, etc. Then we will return the view instance back to
        // the caller for rendering or performing other view manipulations on this.
        $data = array_merge($mergeData, $this->parseData($data));

        return tap($this->viewInstance($view, $path, $data), function ($view) {
            $this->callCreator($view);
        });
    }

    /**
     * Get the first view that actually exists from the given list.
     *
     * @param  array  $views
     * @param  \Voyager\Contracts\NutsAndBolts\Arrayable|array  $data
     * @param  array  $mergeData
     * @return \Mariner\Contracts\View\View
     *
     * @throws \InvalidArgumentException
     */
    public function first(array $views, $data = [], $mergeData = [])
    {
        $view = Arr::first($views, function ($view) {
            return $this->exists($view);
        });

        if (! $view) {
            throw new InvalidArgumentException('None of the views in the given array exist.');
        }

        return $this->make($view, $data, $mergeData);
    }

    /**
     * Get the rendered content of the view based on a given condition.
     *
     * @param  bool  $condition
     * @param  string  $view
     * @param  \Voyager\Contracts\NutsAndBolts\Arrayable|array  $data
     * @param  array  $mergeData
     * @return string
     */
    public function renderWhen($condition, $view, $data = [], $mergeData = [])
    {
        if (! $condition) {
            return '';
        }

        return $this->make($view, $this->parseData($data), $mergeData)->render();
    }

    /**
     * Get the rendered content of the view based on the negation of a given condition.
     *
     * @param  bool  $condition
     * @param  string  $view
     * @param  \Voyager\Contracts\NutsAndBolts\Arrayable|array  $data
     * @param  array  $mergeData
     * @return string
     */
    public function renderUnless($condition, $view, $data = [], $mergeData = [])
    {
        return $this->renderWhen(! $condition, $view, $data, $mergeData);
    }

    /**
     * Get the rendered contents of a partial from a loop.
     *
     * @param  string  $view
     * @param  array  $data
     * @param  string  $iterator
     * @param  string  $empty
     * @return string
     */
    public function renderEach($view, $data, $iterator, $empty = 'raw|')
    {
        $result = '';

        // If is actually data in the array, we will loop through the data and append
        // an instance of the partial view to the final result HTML passing in the
        // iterated value of this data array, allowing the views to access them.
        if (count($data) > 0) {
            foreach ($data as $key => $value) {
                $result .= $this->make(
                    $view, ['key' => $key, $iterator => $value]
                )->render();
            }
        }

        // If there is no data in the array, we will render the contents of the empty
        // view. Alternatively, the "empty view" could be a raw string that begins
        // with "raw|" for convenience and to let this know that it is a string.
        else {
            $result = str_starts_with($empty, 'raw|')
                ? substr($empty, 4)
                : $this->make($empty)->render();
        }

        return $result;
    }

    /**
     * Normalize a view name.
     *
     * @param  string  $name
     * @return string
     */
    protected function normalizeName($name)
    {
        return $this->normalizedNameCache[$name] ??= ViewName::normalize($name);
    }

    /**
     * Parse the given data into a raw array.
     *
     * @param  mixed  $data
     * @return array
     */
    protected function parseData($data)
    {
        return $data instanceof Arrayable ? $data->toArray() : $data;
    }

    /**
     * Create a new view instance from the given arguments.
     *
     * @param  string  $view
     * @param  string  $path
     * @param  \Voyager\Contracts\NutsAndBolts\Arrayable|array  $data
     * @return \Mariner\Contracts\View\View
     */
    protected function viewInstance($view, $path, $data)
    {
        return new View($this, $this->getEngineFromPath($path), $view, $path, $data);
    }

    /**
     * Determine if a given view exists.
     *
     * @param  string  $view
     * @return bool
     */
    public function exists($view)
    {
        try {
            $this->finder->find($view);
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }

    /**
     * Get the appropriate view engine for the given path.
     *
     * @param  string  $path
     * @return \Mariner\Contracts\View\Engine
     *
     * @throws \InvalidArgumentException
     */
    public function getEngineFromPath($path)
    {
        if (isset($this->pathEngineCache[$path])) {
            return $this->engines->resolve($this->pathEngineCache[$path]);
        }

        if (! $extension = $this->getExtension($path)) {
            throw new InvalidArgumentException("Unrecognized extension in file: {$path}.");
        }

        return $this->engines->resolve(
            $this->pathEngineCache[$path] = $this->extensions[$extension]
        );
    }

    /**
     * Get the extension used by the view file.
     *
     * @param  string  $path
     * @return string|null
     */
    protected function getExtension($path)
    {
        $extensions = array_keys($this->extensions);

        return Arr::first($extensions, function ($value) use ($path) {
            return str_ends_with($path, '.'.$value);
        });
    }

    /**
     * Add a piece of shared data to the environment.
     *
     * @param  array|string  $key
     * @param  mixed  $value
     * @return mixed
     */
    public function share($key, $value = null)
    {
        $keys = is_array($key) ? $key : [$key => $value];

        // Shared while handling a request, the data belongs to that request; otherwise (at boot) to every view.
        $shared = $this->handlingRequest() ? $this->renderState()->shared : $this->shared;

        foreach ($keys as $key => $value) {
            $shared[$key] = $value;
        }

        if ($this->handlingRequest()) {
            $this->renderState()->shared = $shared;
        } else {
            $this->shared = $shared;
        }

        return $value;
    }

    /**
     * Increment the rendering counter.
     *
     * @return void
     */
    public function incrementRender()
    {
        $this->renderState()->renderCount++;
    }

    /**
     * Decrement the rendering counter.
     *
     * @return void
     */
    public function decrementRender()
    {
        $this->renderState()->renderCount--;
    }

    /**
     * Check if there are no active render operations.
     *
     * @return bool
     */
    public function doneRendering()
    {
        return $this->renderState()->renderCount === 0;
    }

    /**
     * Determine if the given once token has been rendered.
     *
     * @param  string  $id
     * @return bool
     */
    public function hasRenderedOnce(string $id)
    {
        return isset($this->renderState()->renderedOnce[$id]);
    }

    /**
     * Mark the given once token as having been rendered.
     *
     * @param  string  $id
     * @return void
     */
    public function markAsRenderedOnce(string $id)
    {
        $this->renderState()->renderedOnce[$id] = true;
    }

    /**
     * Add a location to the array of view locations.
     *
     * @param  string  $location
     * @return void
     */
    public function addLocation($location)
    {
        $this->finder->addLocation($location);
    }

    /**
     * Prepend a location to the array of view locations.
     *
     * @param  string  $location
     * @return void
     */
    public function prependLocation($location)
    {
        $this->finder->prependLocation($location);
    }

    /**
     * Add a new namespace to the loader.
     *
     * @param  string  $namespace
     * @param  string|array  $hints
     * @return $this
     */
    public function addNamespace($namespace, $hints)
    {
        $this->finder->addNamespace($namespace, $hints);

        return $this;
    }

    /**
     * Prepend a new namespace to the loader.
     *
     * @param  string  $namespace
     * @param  string|array  $hints
     * @return $this
     */
    public function prependNamespace($namespace, $hints)
    {
        $this->finder->prependNamespace($namespace, $hints);

        return $this;
    }

    /**
     * Replace the namespace hints for the given namespace.
     *
     * @param  string  $namespace
     * @param  string|array  $hints
     * @return $this
     */
    public function replaceNamespace($namespace, $hints)
    {
        $this->finder->replaceNamespace($namespace, $hints);

        return $this;
    }

    /**
     * Register a valid view extension and its engine.
     *
     * @param  string  $extension
     * @param  string  $engine
     * @param  \Closure|null  $resolver
     * @return void
     */
    public function addExtension($extension, $engine, $resolver = null)
    {
        $this->finder->addExtension($extension);

        if (isset($resolver)) {
            $this->engines->register($engine, $resolver);
        }

        unset($this->extensions[$extension]);

        $this->extensions = array_merge([$extension => $engine], $this->extensions);

        $this->pathEngineCache = [];
    }

    /**
     * Flush all of the factory state like sections and stacks.
     *
     * @return void
     */
    public function flushState()
    {
        $this->renderState()->renderCount = 0;
        $this->renderState()->renderedOnce = [];

        $this->flushSections();
        $this->flushStacks();
        $this->flushComponents();
        $this->flushFragments();
    }

    /**
     * Flush all of the section contents if done rendering.
     *
     * @return void
     */
    public function flushStateIfDoneRendering()
    {
        if ($this->doneRendering()) {
            $this->flushState();
        }
    }

    /**
     * Get the extension to engine bindings.
     *
     * @return array
     */
    public function getExtensions()
    {
        return $this->extensions;
    }

    /**
     * Get the engine resolver instance.
     *
     * @return \Mariner\View\Engines\EngineResolver
     */
    public function getEngineResolver()
    {
        return $this->engines;
    }

    /**
     * Get the view finder instance.
     *
     * @return \Mariner\View\ViewFinderInterface
     */
    public function getFinder()
    {
        return $this->finder;
    }

    /**
     * Set the view finder instance.
     *
     * @param  \Mariner\View\ViewFinderInterface  $finder
     * @return void
     */
    public function setFinder(ViewFinderInterface $finder)
    {
        $this->finder = $finder;
    }

    /**
     * Flush the cache of views located by the finder.
     *
     * @return void
     */
    public function flushFinderCache()
    {
        $this->getFinder()->flush();
    }

    /**
     * Get the event dispatcher instance.
     *
     * @return \Voyager\Contracts\Signals\SignalDispatcher
     */
    public function getDispatcher()
    {
        return $this->events;
    }

    /**
     * Set the event dispatcher instance.
     *
     * @param  \Voyager\Contracts\Signals\SignalDispatcher  $events
     * @return void
     */
    public function setDispatcher(SignalDispatcher $events)
    {
        $this->events = $events;
    }

    /**
     * Get the IoC container instance.
     *
     * @return \Voyager\Contracts\Vessel\TheServiceContainer
     */
    public function getContainer()
    {
        return $this->container;
    }

    /**
     * Set the IoC container instance.
     *
     * @param  \Voyager\Contracts\Vessel\TheServiceContainer  $container
     * @return void
     */
    public function setContainer(TheServiceContainer $container)
    {
        $this->container = $container;
    }

    /**
     * Get an item from the shared data.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function shared($key, $default = null)
    {
        return Arr::get($this->getShared(), $key, $default);
    }

    /**
     * Get all of the shared data for the environment.
     *
     * @return array
     */
    public function getShared()
    {
        return array_merge($this->shared, $this->renderState()->shared);
    }

    /**
     * Render per request: the factory renders with the request's RenderState instead of its own.
     *
     * @param  \Closure(): \Mariner\View\RenderState  $stateResolver
     * @param  \Closure(): bool  $handlingRequest  whether a request is being handled right now
     */
    public function useRenderState(Closure $stateResolver, Closure $handlingRequest): void
    {
        $this->renderStateResolver = $stateResolver;
        $this->handlingRequestResolver = $handlingRequest;
    }

    /**
     * The state of the views being rendered for the current request.
     */
    protected function renderState(): RenderState
    {
        return $this->renderStateResolver ? ($this->renderStateResolver)() : ($this->localRenderState ??= new RenderState);
    }

    /**
     * Whether a request is being handled, so data shared now belongs to it.
     */
    protected function handlingRequest(): bool
    {
        return $this->handlingRequestResolver && ($this->handlingRequestResolver)();
    }
}
