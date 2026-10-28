<?php

namespace Mariner\Auth\Events;

use Mariner\Http\Request;

class Lockout
{
    /**
     * The throttled request.
     *
     * @var \Mariner\Http\Request
     */
    public $request;

    /**
     * Create a new event instance.
     *
     * @param  \Mariner\Http\Request  $request
     */
    public function __construct(Request $request)
    {
        $this->request = $request;
    }
}
