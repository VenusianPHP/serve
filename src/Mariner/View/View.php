<?php

namespace Mariner\View;

use ArrayAccess;
use BadMethodCallException;
use Voyager\Contracts\NutsAndBolts\Arrayable;
use Voyager\Contracts\NutsAndBolts\Htmlable;
use Mariner\Contracts\NutsAndBolts\MessageProvider;
use Mariner\Contracts\NutsAndBolts\Renderable;
use Mariner\Contracts\View\Engine;
use Mariner\Contracts\View\View as ViewContract;
use Voyager\NutsAndBolts\Collection;
use Mariner\NutsAndBolts\MessageBag;
use Voyager\NutsAndBolts\Str;
use Voyager\NutsAndBolts\Concerns\Macroable;
use Mariner\NutsAndBolts\ViewErrorBag;
use Stringable;
use Throwable;

class View implements ArrayAccess, Htmlable, Stringable, ViewContract
{
    use Macroable {
        __call as macroCall;
    }

    /**
     * The view factory instance.
     *
     * @var \Mariner\View\Factory
     */
    protected $factory;

    /**
     * The engine implementation.
     *
     * @var \Mariner\Contracts\View\Engine
     */
    protected $engine;

    /**
     * The name of the view.
     *
     * @var string
     */
    protected $view;

    /**
     * The array of view data.
     *
     * @var array
     */
    protected $data;

    /**
     * The path to the view file.
     *
     * @var string
     */
    protected $path;

    /**
     * Create a new view instance.
     *
     * @param  \Mariner\View\Factory  $factory
     * @param  \Mariner\Contracts\View\Engine  $engine
     * @param  string  $view
     * @param  string  $path
     * @param  mixed  $data
     */
    public function __construct(Factory $factory, Engine $engine, $view, $path, $data = [])
    {
        $this->view = $view;
        $this->path = $path;
        $this->engine = $engine;
        $this->factory = $factory;

        $this->data = $data instanceof Arrayable ? $data->toArray() : (array) $data;
    }

    /**
     * Get the evaluated contents of a given fragment.
     *
     * @param  string  $fragment
     * @return string
     */
    public function fragment($fragment)
    {
        return $this->render(function () use ($fragment) {
            return $this->factory->getFragment($fragment);
        });
    }

    /**
     * Get the evaluated contents for a given array of fragments or return all fragments.
     *
     * @param  array|null  $fragments
     * @return string
     */
    public function fragments(?array $fragments = null)
    {
        return is_null($fragments)
            ? $this->allFragments()
            : (new Collection($fragments))->map(fn ($f) => $this->fragment($f))->implode('');
    }

    /**
     * Get the evaluated contents of a given fragment if the given condition is true.
     *
     * @param  bool  $boolean
     * @param  string  $fragment
     * @return string
     */
    public function fragmentIf($boolean, $fragment)
    {
        if (value($boolean)) {
            return $this->fragment($fragment);
        }

        return $this->render();
    }

    /**
     * Get the evaluated contents for a given array of fragments if the given condition is true.
     *
     * @param  bool  $boolean
     * @param  array|null  $fragments
     * @return string
     */
    public function fragmentsIf($boolean, ?array $fragments = null)
    {
        if (value($boolean)) {
            return $this->fragments($fragments);
        }

        return $this->render();
    }

    /**
     * Get all fragments as a single string.
     *
     * @return string
     */
    protected function allFragments()
    {
        return (new Collection($this->render(fn () => $this->factory->getFragments())))->implode('');
    }

    /**
     * Get the string contents of the view.
     *
     * @param  callable|null  $callback
     * @return ($callback is null ? string : mixed)
     *
     * @throws \Throwable
     */
    public function render(?callable $callback = null)
    {
        try {
            $contents = $this->renderContents();

            $response = isset($callback) ? $callback($this, $contents) : null;

            // Once we have the contents of the view, we will flush the sections if we are
            // done rendering all views so that there is nothing left hanging over when
            // another view gets rendered in the future by the application developer.
            $this->factory->flushStateIfDoneRendering();

            return ! is_null($response) ? $response : $contents;
        } catch (Throwable $e) {
            $this->factory->flushState();

            throw $e;
        }
    }

    /**
     * Get the contents of the view instance.
     *
     * @return string
     */
    protected function renderContents()
    {
        // We will keep track of the number of views being rendered so we can flush
        // the section after the complete rendering operation is done. This will
        // clear out the sections for any separate views that may be rendered.
        $this->factory->incrementRender();

        $this->factory->callComposer($this);

        $contents = $this->getContents();

        // Once we've finished rendering the view, we'll decrement the render count
        // so that each section gets flushed out next time a view is created and
        // no old sections are staying around in the memory of an environment.
        $this->factory->decrementRender();

        return $contents;
    }

    /**
     * Get the evaluated contents of the view.
     *
     * @return string
     */
    protected function getContents()
    {
        return $this->engine->get($this->path, $this->gatherData());
    }

    /**
     * Get the data bound to the view instance.
     *
     * @return array
     */
    public function gatherData()
    {
        $data = array_merge($this->factory->getShared(), $this->data);

        foreach ($data as $key => $value) {
            if ($value instanceof Renderable) {
                $data[$key] = $value->render();
            }
        }

        return $data;
    }

    /**
     * Get the sections of the rendered view.
     *
     * @return array
     *
     * @throws \Throwable
     */
    public function renderSections()
    {
        return $this->render(function () {
            return $this->factory->getSections();
        });
    }

    /**
     * Add a piece of data to the view.
     *
     * @param  string|array  $key
     * @param  mixed  $value
     * @return $this
     */
    public function with($key, $value = null)
    {
        if (is_array($key)) {
            $this->data = array_merge($this->data, $key);
        } else {
            $this->data[$key] = $value;
        }

        return $this;
    }

    /**
     * Add a view instance to the view data.
     *
     * @param  string  $key
     * @param  string  $view
     * @param  array  $data
     * @return $this
     */
    public function nest($key, $view, array $data = [])
    {
        return $this->with($key, $this->factory->make($view, $data));
    }

    /**
     * Add validation errors to the view.
     *
     * @param  \Mariner\Contracts\NutsAndBolts\MessageProvider|array|string  $provider
     * @param  string  $bag
     * @return $this
     */
    public function withErrors($provider, $bag = 'default')
    {
        return $this->with('errors', (new ViewErrorBag)->put(
            $bag, $this->formatErrors($provider)
        ));
    }

    /**
     * Parse the given errors into an appropriate value.
     *
     * @param  \Mariner\Contracts\NutsAndBolts\MessageProvider|array|string  $provider
     * @return \Mariner\Contracts\NutsAndBolts\MessageBag
     */
    protected function formatErrors($provider)
    {
        return $provider instanceof MessageProvider
            ? $provider->getMessageBag()
            : new MessageBag((array) $provider);
    }

    /**
     * Get the name of the view.
     *
     * @return string
     */
    public function name()
    {
        return $this->getName();
    }

    /**
     * Get the name of the view.
     *
     * @return string
     */
    public function getName()
    {
        return $this->view;
    }

    /**
     * Get the array of view data.
     *
     * @return array
     */
    public function getData()
    {
        return $this->data;
    }

    /**
     * Get the path to the view file.
     *
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * Set the path to the view.
     *
     * @param  string  $path
     * @return void
     */
    public function setPath($path)
    {
        $this->path = $path;
    }

    /**
     * Get the view factory instance.
     *
     * @return \Mariner\View\Factory
     */
    public function getFactory()
    {
        return $this->factory;
    }

    /**
     * Get the view's rendering engine.
     *
     * @return \Mariner\Contracts\View\Engine
     */
    public function getEngine()
    {
        return $this->engine;
    }

    /**
     * Determine if a piece of data is bound.
     *
     * @param  string  $offset
     * @return bool
     */
    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->data);
    }

    /**
     * Get a piece of bound data to the view.
     *
     * @param  string  $offset
     * @return mixed
     */
    public function offsetGet($offset): mixed
    {
        return $this->data[$offset];
    }

    /**
     * Set a piece of data on the view.
     *
     * @param  string  $offset
     * @param  mixed  $value
     * @return void
     */
    public function offsetSet($offset, $value): void
    {
        $this->with($offset, $value);
    }

    /**
     * Unset a piece of data from the view.
     *
     * @param  string  $offset
     * @return void
     */
    public function offsetUnset($offset): void
    {
        unset($this->data[$offset]);
    }

    /**
     * Get a piece of data from the view.
     *
     * @param  string  $key
     * @return mixed
     */
    public function &__get($key)
    {
        return $this->data[$key];
    }

    /**
     * Set a piece of data on the view.
     *
     * @param  string  $key
     * @param  mixed  $value
     * @return void
     */
    public function __set($key, $value)
    {
        $this->with($key, $value);
    }

    /**
     * Check if a piece of data is bound to the view.
     *
     * @param  string  $key
     * @return bool
     */
    public function __isset($key)
    {
        return isset($this->data[$key]);
    }

    /**
     * Remove a piece of bound data from the view.
     *
     * @param  string  $key
     * @return void
     */
    public function __unset($key)
    {
        unset($this->data[$key]);
    }

    /**
     * Dynamically bind parameters to the view.
     *
     * @param  string  $method
     * @param  array  $parameters
     * @return \Mariner\View\View
     *
     * @throws \BadMethodCallException
     */
    public function __call($method, $parameters)
    {
        if (static::hasMacro($method)) {
            return $this->macroCall($method, $parameters);
        }

        if (! str_starts_with($method, 'with')) {
            throw new BadMethodCallException(sprintf(
                'Method %s::%s does not exist.', static::class, $method
            ));
        }

        return $this->with(Str::camel(substr($method, 4)), $parameters[0]);
    }

    /**
     * Get content as a string of HTML.
     *
     * @return string
     */
    public function toHtml(): string
    {
        return $this->render();
    }

    /**
     * Get the string contents of the view.
     *
     * @return string
     *
     * @throws \Throwable
     */
    public function __toString()
    {
        return $this->render();
    }
}
