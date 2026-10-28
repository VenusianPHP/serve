<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\Routing\Controllers\HasMiddleware;
use Mariner\Routing\Controllers\Middleware;
use Mariner\NutsAndBolts\Facades\Route;

test('has middleware is respected', function () {
    $route = Route::get('/', [HasMiddlewareTestController::class, 'index']);
    expect($route->controllerMiddleware())->toEqual(['all', 'only-index']);

    $route = Route::get('/', [HasMiddlewareTestController::class, 'show']);
    expect($route->controllerMiddleware())->toEqual(['all', 'except-index']);
});

class HasMiddlewareTestController implements HasMiddleware
{
    public static function middleware()
    {
        return [
            new Middleware('all'),
            (new Middleware('only-index'))->only('index'),
            (new Middleware('except-index'))->except('index'),
        ];
    }

    public function index()
    {
        //
    }

    public function show()
    {
    }
}
