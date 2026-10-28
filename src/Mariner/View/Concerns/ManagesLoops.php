<?php

namespace Mariner\View\Concerns;

use Voyager\NutsAndBolts\DataObjects\Arr;
use Voyager\NutsAndBolts\LazyCollection;

trait ManagesLoops
{

    /**
     * Add new loop to the stack.
     *
     * @param  \Countable|array  $data
     * @return void
     */
    public function addLoop($data)
    {
        $length = is_countable($data) && ! $data instanceof LazyCollection
            ? count($data)
            : null;

        $parent = Arr::last($this->renderState()->loopsStack);

        $this->renderState()->loopsStack[] = [
            'iteration' => 0,
            'index' => 0,
            'remaining' => $length ?? null,
            'count' => $length,
            'first' => true,
            'last' => isset($length) ? $length === 1 : null,
            'odd' => false,
            'even' => true,
            'depth' => count($this->renderState()->loopsStack) + 1,
            'parent' => $parent ? (object) $parent : null,
        ];
    }

    /**
     * Increment the top loop's indices.
     *
     * @return void
     */
    public function incrementLoopIndices()
    {
        $loop = $this->renderState()->loopsStack[$index = count($this->renderState()->loopsStack) - 1];

        $this->renderState()->loopsStack[$index] = array_merge($this->renderState()->loopsStack[$index], [
            'iteration' => $loop['iteration'] + 1,
            'index' => $loop['iteration'],
            'first' => $loop['iteration'] === 0,
            'odd' => ! $loop['odd'],
            'even' => ! $loop['even'],
            'remaining' => isset($loop['count']) ? $loop['remaining'] - 1 : null,
            'last' => isset($loop['count']) ? $loop['iteration'] == $loop['count'] - 1 : null,
        ]);
    }

    /**
     * Pop a loop from the top of the loop stack.
     *
     * @return void
     */
    public function popLoop()
    {
        array_pop($this->renderState()->loopsStack);
    }

    /**
     * Get an instance of the last loop in the stack.
     *
     * @return \stdClass|null
     */
    public function getLastLoop()
    {
        if ($last = Arr::last($this->renderState()->loopsStack)) {
            return (object) $last;
        }

        return null;
    }

    /**
     * Get the entire loop stack.
     *
     * @return array
     */
    public function getLoopStack()
    {
        return $this->renderState()->loopsStack;
    }
}
