<?php

namespace Mariner\Routing\Events;

class RouteMatched
{
    /**
     * Create a new event instance.
     *
     * @param  \Mariner\Routing\Route  $route  The route instance.
     * @param  \Mariner\Http\Request  $request  The request instance.
     */
    public function __construct(
        public $route,
        public $request,
    ) {
    }
}
