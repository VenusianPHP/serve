<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\Routing\Attributes\Controllers\WithoutMiddleware;
use Mariner\NutsAndBolts\Facades\Route;

test('attribute without middleware is respected', function () {
    $route = Route::get('/', [WithoutMiddlewareAttributeController::class, 'index']);
    expect($route->excludedMiddleware())->toEqual([
        'all',
        'only-index',
        'also-index',
    ]);

    $route = Route::get('/', [WithoutMiddlewareAttributeController::class, 'show'])->withoutMiddleware('merged');
    expect($route->excludedMiddleware())->toEqual([
        'merged',
        'all',
        'except-index',
    ]);

    $route = Route::get('/', [ChildWithoutMiddlewareAttributeController::class, 'index']);
    expect($route->excludedMiddleware())->toEqual([
        'all',
        'only-index',
        'also-index',
    ]);

    $route = Route::get('/', [ChildWithoutMiddlewareAttributeController::class, 'show'])->withoutMiddleware('merged');
    expect($route->excludedMiddleware())->toEqual([
        'merged',
        'all',
        'except-index',
    ]);
});

#[WithoutMiddleware('all')]
#[WithoutMiddleware('only-index', only: ['index'])]
#[WithoutMiddleware('except-index', except: ['index'])]
class WithoutMiddlewareAttributeController
{
    #[WithoutMiddleware('also-index')]
    public function index(): void
    {
        // ...
    }

    public function show(): void
    {
        // ...
    }
}

class ChildWithoutMiddlewareAttributeController extends WithoutMiddlewareAttributeController
{
}
