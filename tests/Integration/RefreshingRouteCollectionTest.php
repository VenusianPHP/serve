<?php

namespace Venusian\Serve\Tests\Integration;

use Mariner\Routing\Route;
use Mockery;

it('rebuilds the lookups only when a lookup misses', function () {
    $routes = Mockery::mock(RefreshingRouteCollection::class)->makePartial();
    $routes->add(new Route(['GET'], 'posts', ['uses' => fn () => 'ok', 'as' => 'posts.index']));

    $routes->shouldReceive('refreshNameLookups')->never();
    $routes->shouldReceive('refreshActionLookups')->never();

    expect($routes->getByName('posts.index'))->not->toBeNull();
});

it('finds a route named after it joined the collection', function () {
    $routes = new RefreshingRouteCollection;
    $route = new Route(['GET'], 'posts/{post}', ['uses' => fn () => 'ok']);
    $routes->add($route);
    $route->name('posts.show');

    expect($routes->getByName('posts.show'))->toBe($route);
});

it('answers null for a name no route has, after one rebuild', function () {
    $routes = Mockery::mock(RefreshingRouteCollection::class)->makePartial();
    $routes->shouldReceive('refreshNameLookups')->once()->passthru();

    expect($routes->getByName('posts.missing'))->toBeNull();
});

it('finds a route whose controller action was set after it joined the collection', function () {
    $routes = new RefreshingRouteCollection;
    $route = new Route(['GET'], 'posts', ['uses' => 'PostController@index']);
    $routes->add($route);
    $route->setAction(array_merge($route->getAction(), ['controller' => 'PostController@index']));

    expect($routes->getByAction('PostController@index'))->toBe($route);
});
