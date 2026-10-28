<?php

namespace Mariner\View\Concerns;

use Voyager\Contracts\NutsAndBolts\Htmlable;
use Mariner\Contracts\View\View;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Mariner\View\ComponentSlot;

trait ManagesComponents
{





    /**
     * Start a component rendering process.
     *
     * @param  \Mariner\Contracts\View\View|\Voyager\Contracts\NutsAndBolts\Htmlable|\Closure|string  $view
     * @param  array  $data
     * @return void
     */
    public function startComponent($view, array $data = [])
    {
        if (ob_start()) {
            $this->renderState()->componentStack[] = $view;

            $this->renderState()->componentData[$this->currentComponent()] = $data;

            $this->renderState()->slots[$this->currentComponent()] = [];
        }
    }

    /**
     * Get the first view that actually exists from the given list, and start a component.
     *
     * @param  array  $names
     * @param  array  $data
     * @return void
     */
    public function startComponentFirst(array $names, array $data = [])
    {
        $name = Arr::first($names, function ($item) {
            return $this->exists($item);
        });

        $this->startComponent($name, $data);
    }

    /**
     * Render the current component.
     *
     * @return string
     */
    public function renderComponent()
    {
        $view = array_pop($this->renderState()->componentStack);

        $this->renderState()->currentComponentData = array_merge(
            $previousComponentData = $this->renderState()->currentComponentData,
            $data = $this->componentData()
        );

        try {
            $view = value($view, $data);

            if ($view instanceof View) {
                return $view->with($data)->render();
            } elseif ($view instanceof Htmlable) {
                return $view->toHtml();
            } else {
                return $this->make($view, $data)->render();
            }
        } finally {
            $this->renderState()->currentComponentData = $previousComponentData;
        }
    }

    /**
     * Get the data for the given component.
     *
     * @return array
     */
    protected function componentData()
    {
        $defaultSlot = new ComponentSlot(trim(ob_get_clean()));

        $slots = array_merge([
            '__default' => $defaultSlot,
        ], $this->renderState()->slots[count($this->renderState()->componentStack)]);

        return array_merge(
            $this->renderState()->componentData[count($this->renderState()->componentStack)],
            ['slot' => $defaultSlot],
            $this->renderState()->slots[count($this->renderState()->componentStack)],
            ['__mariner_slots' => $slots]
        );
    }

    /**
     * Get an item from the component data that exists above the current component.
     *
     * @param  string  $key
     * @param  mixed  $default
     * @return mixed
     */
    public function getConsumableComponentData($key, $default = null)
    {
        if (array_key_exists($key, $this->renderState()->currentComponentData)) {
            return $this->renderState()->currentComponentData[$key];
        }

        $currentComponent = count($this->renderState()->componentStack);

        if ($currentComponent === 0) {
            return value($default);
        }

        for ($i = $currentComponent - 1; $i >= 0; $i--) {
            $data = $this->renderState()->componentData[$i] ?? [];

            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        return value($default);
    }

    /**
     * Start the slot rendering process.
     *
     * @param  string  $name
     * @param  string|null  $content
     * @param  array  $attributes
     * @return void
     */
    public function slot($name, $content = null, $attributes = [])
    {
        if (func_num_args() === 2 || $content !== null) {
            $this->renderState()->slots[$this->currentComponent()][$name] = $content;
        } elseif (ob_start()) {
            $this->renderState()->slots[$this->currentComponent()][$name] = '';

            $this->renderState()->slotStack[$this->currentComponent()][] = [$name, $attributes];
        }
    }

    /**
     * Save the slot content for rendering.
     *
     * @return void
     */
    public function endSlot()
    {
        last($this->renderState()->componentStack);

        $currentSlot = array_pop(
            $this->renderState()->slotStack[$this->currentComponent()]
        );

        [$currentName, $currentAttributes] = $currentSlot;

        $this->renderState()->slots[$this->currentComponent()][$currentName] = new ComponentSlot(
            trim(ob_get_clean()), $currentAttributes
        );
    }

    /**
     * Get the index for the current component.
     *
     * @return int
     */
    protected function currentComponent()
    {
        return count($this->renderState()->componentStack) - 1;
    }

    /**
     * Flush all of the component state.
     *
     * @return void
     */
    protected function flushComponents()
    {
        $this->renderState()->componentStack = [];
        $this->renderState()->componentData = [];
        $this->renderState()->currentComponentData = [];
        $this->renderState()->slots = [];
        $this->renderState()->slotStack = [];
    }
}
