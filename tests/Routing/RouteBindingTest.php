<?php

namespace Venusian\Serve\Tests\Routing;

use Mariner\Routing\Concerns\BindsToRoutes;
use Mariner\Contracts\Routing\UrlRoutable;
use Voyager\Vessel\ControlPanel;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\ModelNotFoundException;
use Voyager\Database\Instrument\SoftDeletes;
use Mariner\Routing\Route;
use Mariner\Routing\RouteBinding;

test('it can resolve the explicit model for the given route', function () {
    $container = ControlPanel::getInstance();

    $route = new Route('GET', '/users/{user}', function () {
    });

    $callback = RouteBinding::forModel($container, ExplicitRouteBindingUser::class);
    expect($callback(1, $route))->toBeInstanceOf(ExplicitRouteBindingUser::class);
});

test('it cannot resolve the explicit soft deleted model for the given route', function () {
    $container = ControlPanel::getInstance();

    $route = new Route('GET', '/users/{user}', function () {
    });

    $callback = RouteBinding::forModel($container, ExplicitRouteBindingSoftDeletableUser::class);
    $callback(1, $route);
})->throws(ModelNotFoundException::class);

test('it can resolve the explicit soft deleted model for the given route with trashed', function () {
    $container = ControlPanel::getInstance();

    $route = (new Route('GET', '/users/{user}', function () {
    }))->withTrashed();

    $callback = RouteBinding::forModel($container, ExplicitRouteBindingSoftDeletableUser::class);
    expect($callback(1, $route))->toBeInstanceOf(ExplicitRouteBindingSoftDeletableUser::class);
});

class ExplicitRouteBindingUser extends Model implements UrlRoutable
{
    use BindsToRoutes;

    public function resolveRouteBinding($value, $field = null)
    {
        return new self();
    }
}

class ExplicitRouteBindingSoftDeletableUser extends Model implements UrlRoutable
{
    use BindsToRoutes, SoftDeletes;

    public function resolveRouteBinding($value, $field = null)
    {
        return null;
    }

    public function resolveSoftDeletableRouteBinding($value, $field = null)
    {
        return new self();
    }
}
