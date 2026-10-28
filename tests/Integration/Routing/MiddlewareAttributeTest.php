<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\Routing\Attributes\Controllers\Middleware;
use Mariner\NutsAndBolts\Facades\Route;

test('attribute middleware is respected', function () {
    $route = Route::get('/', [MiddlewareAttributeController::class, 'index']);
    expect($route->controllerMiddleware())->toEqual([
        'all',
        'only-index',
        'also-index',
    ]);

    $route = Route::get('/', [MiddlewareAttributeController::class, 'show']);
    expect($route->controllerMiddleware())->toEqual([
        'all',
        'except-index',
    ]);
});

#[Middleware('all')]
#[Middleware('only-index', only: ['index'])]
#[Middleware('except-index', except: ['index'])]
class MiddlewareAttributeController
{
    #[Middleware('also-index')]
    public function index(): void
    {
        // ...
    }

    public function show(): void
    {
        // ...
    }
}
