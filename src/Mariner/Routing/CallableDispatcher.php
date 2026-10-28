<?php

namespace Mariner\Routing;

use Voyager\Vessel\ControlPanel;
use Mariner\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use ReflectionFunction;

class CallableDispatcher implements CallableDispatcherContract
{
    use ResolvesRouteDependencies;

    /**
     * The container instance.
     *
     * @var \Voyager\Vessel\ControlPanel
     */
    protected $container;

    /**
     * Create a new callable dispatcher instance.
     *
     * @param  \Voyager\Vessel\ControlPanel  $container
     */
    public function __construct(ControlPanel $container)
    {
        $this->container = $container;
    }

    /**
     * Dispatch a request to a given callable.
     *
     * @param  \Mariner\Routing\Route  $route
     * @param  callable  $callable
     * @return mixed
     */
    public function dispatch(Route $route, $callable)
    {
        return $callable(...array_values($this->resolveParameters($route, $callable)));
    }

    /**
     * Resolve the parameters for the callable.
     *
     * @param  \Mariner\Routing\Route  $route
     * @param  callable  $callable
     * @return array
     */
    protected function resolveParameters(Route $route, $callable)
    {
        return $this->resolveMethodDependencies($route->parametersWithoutNulls(), new ReflectionFunction($callable));
    }
}
