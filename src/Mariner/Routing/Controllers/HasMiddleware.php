<?php

namespace Mariner\Routing\Controllers;

interface HasMiddleware
{
    /**
     * Get the middleware that should be assigned to the controller.
     *
     * @return array<int,\Mariner\Routing\Controllers\Middleware|\Closure|string>
     */
    public static function middleware();
}
