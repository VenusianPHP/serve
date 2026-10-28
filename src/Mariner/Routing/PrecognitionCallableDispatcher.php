<?php

namespace Mariner\Routing;

class PrecognitionCallableDispatcher extends CallableDispatcher
{
    /**
     * Resolve the callable's parameters, which runs their validation, then answer without calling it.
     *
     * @param  \Mariner\Routing\Route  $route
     * @param  callable  $callable
     * @return mixed
     */
    public function dispatch(Route $route, $callable)
    {
        $this->resolveParameters($route, $callable);

        abort(204, headers: ['Precognition-Success' => 'true']);
    }
}
