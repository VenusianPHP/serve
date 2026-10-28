<?php

namespace Mariner\Routing\Events;

class Routing
{
    /**
     * Create a new event instance.
     *
     * @param  \Mariner\Http\Request  $request  The request instance.
     */
    public function __construct(
        public $request,
    ) {
    }
}
