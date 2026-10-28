<?php

namespace Mariner\Routing\Matching;

use Mariner\Http\Request;
use Mariner\Routing\Route;

interface ValidatorInterface
{
    /**
     * Validate a given rule against a route and request.
     *
     * @param  \Mariner\Routing\Route  $route
     * @param  \Mariner\Http\Request  $request
     * @return bool
     */
    public function matches(Route $route, Request $request);
}
