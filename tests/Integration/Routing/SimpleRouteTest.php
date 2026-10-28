<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\NutsAndBolts\Facades\Route;
use Venusian\Serve\Tests\Routing\Fixtures\RouteNameEnum;

test('simple route through the framework', function () {
    Route::get('/', function () {
        return 'Hello World';
    });

    $response = $this->get('/');

    expect($response->content())->toBe('Hello World');

    $response = $this->get('/?foo=bar');

    expect($response->content())->toBe('Hello World');

    expect($response->baseRequest->query('foo'))->toBe('bar');
});

test('simple route wit string backed enum route name through the framework', function () {
    Route::get('/', function () {
        return 'Hello World';
    })->name(RouteNameEnum::UserIndex);

    $response = $this->get(\route(RouteNameEnum::UserIndex, ['foo' => 'bar']));

    expect($response->content())->toBe('Hello World');

    expect($response->baseRequest->query('foo'))->toBe('bar');
});
