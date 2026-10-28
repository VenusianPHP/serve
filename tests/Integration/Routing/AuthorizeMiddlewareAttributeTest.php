<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\Routing\Attributes\Controllers\Authorize;
use Mariner\NutsAndBolts\Facades\Route;

test('attribute is respected', function () {
    $route = Route::get('/', [AuthorizeMiddlewareAttributeController::class, 'index']);
    expect($route->controllerMiddleware())->toEqual([
        'Mariner\Auth\Middleware\Authorize:all',
        'Mariner\Auth\Middleware\Authorize:only-index,a',
        'Mariner\Auth\Middleware\Authorize:also-index',
    ]);

    $route = Route::get('/', [AuthorizeMiddlewareAttributeController::class, 'show']);
    expect($route->controllerMiddleware())->toEqual([
        'Mariner\Auth\Middleware\Authorize:all',
        'Mariner\Auth\Middleware\Authorize:except-index,a,b',
    ]);
});

#[Authorize('all')]
#[Authorize('only-index', 'a', only: ['index'])]
#[Authorize('except-index', ['a', 'b'], except: ['index'])]
class AuthorizeMiddlewareAttributeController
{
    #[Authorize('also-index')]
    public function index(): void
    {
        // ...
    }

    public function show(): void
    {
        // ...
    }
}
