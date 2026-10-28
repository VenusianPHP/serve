<?php

namespace Mariner\Http\Middleware;

use Closure;
use Mariner\Http\Request;
use Voyager\NutsAndBolts\Defer\DeferredCallbackCollection;
use Symfony\Component\HttpFoundation\Response;

class InvokeDeferredCallbacks
{
    /**
     * Handle the incoming request.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Closure $next)
    {
        return $next($request);
    }

    /**
     * Invoke the deferred callbacks.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     * @return void
     */
    public function terminate(Request $request, Response $response)
    {
        app(DeferredCallbackCollection::class)->invokeWhen(fn ($callback) => $response->getStatusCode() < 400 || $callback->always);
    }
}
