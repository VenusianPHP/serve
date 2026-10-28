<?php

namespace Mariner\Routing\Matching;

use Mariner\Http\Request;
use Mariner\Routing\Route;

class MethodValidator implements ValidatorInterface
{
    /**
     * Validate a given rule against a route and request.
     *
     * @param  \Mariner\Routing\Route  $route
     * @param  \Mariner\Http\Request  $request
     * @return bool
     */
    public function matches(Route $route, Request $request)
    {
        return in_array($request->getMethod(), $route->methods());
    }
}
