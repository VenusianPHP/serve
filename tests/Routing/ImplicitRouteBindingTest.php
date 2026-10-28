<?php

namespace Venusian\Serve\Tests\Routing;

use Mariner\Routing\Concerns\BindsToRoutes;
use Mariner\Contracts\Routing\UrlRoutable;
use Voyager\Vessel\ControlPanel;
use Voyager\Database\Instrument\Model;
use Mariner\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Mariner\Routing\ImplicitRouteBinding;
use Mariner\Routing\Route;
use Venusian\Serve\Tests\Routing\Fixtures\CategoryBackedEnum;
use Venusian\Serve\Tests\Routing\Fixtures\CategoryEnum;

require_once __DIR__.'/Fixtures/Enums.php';

test('it can resolve the implicit backed enum route bindings for the given route', function () {
    $action = ['uses' => function (CategoryBackedEnum $category) {
        return $category->value;
    }];

    $route = new Route('GET', '/test', $action);
    $route->parameters = ['category' => 'fruits'];

    $route->prepareForSerialization();

    $container = ControlPanel::getInstance();

    ImplicitRouteBinding::resolveForRoute($container, $route);

    expect($route->parameter('category')->value)->toBe('fruits');
});

test('it can resolve the implicit backed enum route bindings for the given route with optional parameter', function () {
    $action = ['uses' => function (?CategoryBackedEnum $category = null) {
        return $category->value;
    }];

    $route = new Route('GET', '/test', $action);
    $route->parameters = ['category' => 'fruits'];

    $route->prepareForSerialization();

    $container = ControlPanel::getInstance();

    ImplicitRouteBinding::resolveForRoute($container, $route);

    expect($route->parameter('category')->value)->toBe('fruits');
});

test('it handles optional implicit backed enum route bindings for the given route with optional parameter', function () {
    $action = ['uses' => function (?CategoryBackedEnum $category = null) {
        return $category->value;
    }];

    $route = new Route('GET', '/test', $action);
    $route->parameters = ['category' => null];

    $route->prepareForSerialization();

    $container = ControlPanel::getInstance();

    ImplicitRouteBinding::resolveForRoute($container, $route);

    expect($route->parameter('category'))->toBeNull();
});

test('it does not resolve implicit non backed enum route bindings for the given route', function () {
    $action = ['uses' => function (CategoryEnum $category) {
        return $category->value;
    }];

    $route = new Route('GET', '/test', $action);
    $route->parameters = ['category' => 'fruits'];

    $route->prepareForSerialization();

    $container = ControlPanel::getInstance();

    ImplicitRouteBinding::resolveForRoute($container, $route);

    expect($route->parameter('category'))->toBeString();
    expect($route->parameter('category'))->toBe('fruits');
});

test('implicit backed enum internal exception', function () {
    $action = ['uses' => function (CategoryBackedEnum $category) {
        return $category->value;
    }];

    $route = new Route('GET', '/test', $action);
    $route->parameters = ['category' => 'cars'];

    $route->prepareForSerialization();

    $container = ControlPanel::getInstance();

    ImplicitRouteBinding::resolveForRoute($container, $route);
})->throws(BackedEnumCaseNotFoundException::class, 'Case [cars] not found on Backed Enum ['.CategoryBackedEnum::class.'].');

test('it can resolve the implicit model route bindings for the given route', function () {
    $action = ['uses' => function (ImplicitRouteBindingUser $user) {
        return $user;
    }];

    $route = new Route('GET', '/test', $action);
    $route->parameters = ['user' => $user = new ImplicitRouteBindingUser];

    $route->prepareForSerialization();

    $container = ControlPanel::getInstance();

    ImplicitRouteBinding::resolveForRoute($container, $route);

    expect($route->parameter('user'))->toBe($user);
});

class ImplicitRouteBindingUser extends Model implements UrlRoutable
{
    use BindsToRoutes;
}
