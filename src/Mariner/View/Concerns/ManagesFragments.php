<?php

namespace Mariner\View\Concerns;

use InvalidArgumentException;

trait ManagesFragments
{


    /**
     * Start injecting content into a fragment.
     *
     * @param  string  $fragment
     * @return void
     */
    public function startFragment($fragment)
    {
        if (ob_start()) {
            $this->renderState()->fragmentStack[] = $fragment;
        }
    }

    /**
     * Stop injecting content into a fragment.
     *
     * @return string
     *
     * @throws \InvalidArgumentException
     */
    public function stopFragment()
    {
        if (empty($this->renderState()->fragmentStack)) {
            throw new InvalidArgumentException('Cannot end a fragment without first starting one.');
        }

        $last = array_pop($this->renderState()->fragmentStack);

        $this->renderState()->fragments[$last] = ob_get_clean();

        return $this->renderState()->fragments[$last];
    }

    /**
     * Get the contents of a fragment.
     *
     * @param  string  $name
     * @param  string|null  $default
     * @return mixed
     */
    public function getFragment($name, $default = null)
    {
        return $this->getFragments()[$name] ?? $default;
    }

    /**
     * Get the entire array of rendered fragments.
     *
     * @return array
     */
    public function getFragments()
    {
        return $this->renderState()->fragments;
    }

    /**
     * Flush all of the fragments.
     *
     * @return void
     */
    public function flushFragments()
    {
        $this->renderState()->fragments = [];
        $this->renderState()->fragmentStack = [];
    }
}
