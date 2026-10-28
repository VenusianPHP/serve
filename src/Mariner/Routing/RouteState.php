<?php

namespace Mariner\Routing;

use Mariner\Http\Request;

/**
 * The route and request the router is dispatching right now.
 *
 * Registered with scoped(), so concurrent request fibers each see their own current route and
 * request, while the Router singleton keeps the routes, middleware and binders registered at boot.
 */
final class RouteState
{
    public ?Route $current = null;

    public ?Request $request = null;
}
