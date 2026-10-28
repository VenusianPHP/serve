<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\NutsAndBolts\Facades\Route;

test('middleware run when registered as array or params', function () {
    $controller = function () {
        return 'Hello World';
    };

    Route::middleware(Middleware::class, Middleware2::class)
        ->get('before', $controller);

    Route::get('after', $controller)
        ->middleware(Middleware::class, Middleware2::class);

    Route::middleware([Middleware::class, Middleware2::class])
        ->get('before_array', $controller);

    Route::get('after_array', $controller)
        ->middleware([Middleware::class, Middleware2::class]);

    Route::middleware(Middleware::class)
        ->get('before_after', $controller)
        ->middleware([Middleware2::class]);

    Route::middleware(Middleware::class)
        ->middleware(Middleware2::class)
        ->get('both_before', $controller);

    Route::get('both_after', $controller)
        ->middleware(Middleware::class)
        ->middleware(Middleware2::class);

    expect($this->get('before')->content())->toBe('1_2');
    expect($this->get('after')->content())->toBe('1_2');
    expect($this->get('before_array')->content())->toBe('1_2');
    expect($this->get('after_array')->content())->toBe('1_2');
    expect($this->get('before_after')->content())->toBe('1_2');
    expect($this->get('both_before')->content())->toBe('1_2');
    expect($this->get('both_after')->content())->toBe('1_2');
    expect($this->get('both_after')->content())->toBe('1_2');
});

test('empty middleware group are handled gracefully', function () {
    $controller = function () {
        return 'Hello World';
    };

    Route::middlewareGroup('public', []);

    Route::middleware('public')
        ->get('public', $controller);

    expect($this->get('public')->content())->toBe('Hello World');
});

final class FluentRoutingValue
{
    public static ?string $value = null;
}

class Middleware
{
    public function handle($request, $next)
    {
        FluentRoutingValue::$value = '1';

        return $next($request);
    }
}

class Middleware2
{
    public function handle()
    {
        return FluentRoutingValue::$value.'_2';
    }
}
