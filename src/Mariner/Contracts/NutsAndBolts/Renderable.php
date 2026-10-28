<?php

namespace Mariner\Contracts\NutsAndBolts;

interface Renderable
{
    /**
     * Get the evaluated contents of the object.
     *
     * @return string
     */
    public function render();
}
