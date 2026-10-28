<?php

namespace Mariner\Routing\Middleware;

use Closure;
use Mariner\Contracts\Routing\Registrar;
use Voyager\Database\Instrument\ModelNotFoundException;

class SubstituteBindings
{
    /**
     * The router instance.
     *
     * @var \Mariner\Contracts\Routing\Registrar
     */
    protected $router;

    /**
     * Create a new bindings substitutor.
     *
     * @param  \Mariner\Contracts\Routing\Registrar  $router
     */
    public function __construct(Registrar $router)
    {
        $this->router = $router;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     *
     * @throws \Voyager\Database\Instrument\ModelNotFoundException
     */
    public function handle($request, Closure $next)
    {
        $route = $request->route();

        try {
            $this->router->substituteBindings($route);
            $this->router->substituteImplicitBindings($route);
        } catch (ModelNotFoundException $exception) {
            if ($route->getMissing()) {
                return $route->getMissing()($request, $exception);
            }

            throw $exception;
        }

        return $next($request);
    }
}
