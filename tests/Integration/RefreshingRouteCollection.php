<?php

namespace Venusian\Serve\Tests\Integration;

use Mariner\Routing\RouteCollection;

/**
 * Stands in for Testbench's lookup refresh. Test harness only: src keeps Laravel's behaviour.
 *
 * A route named, or pointed at a controller, after it joined the collection (Route::get(...)->name('foo')) is only in
 * the name and action lookups once they are rebuilt, which an app does when it boots. Laravel's integration tests run on
 * an app that rebuilds them after routes are defined; this collection rebuilds them when a lookup misses, so a test can
 * define a route and generate its URL in the same breath. A lookup that hits never rebuilds.
 */
class RefreshingRouteCollection extends RouteCollection
{
    public function getByName($name)
    {
        $route = parent::getByName($name);

        if ($route === null) {
            $this->refreshNameLookups();
            $route = parent::getByName($name);
        }

        return $route;
    }

    public function getByAction($action)
    {
        $route = parent::getByAction($action);

        if ($route === null) {
            $this->refreshActionLookups();
            $route = parent::getByAction($action);
        }

        return $route;
    }
}
