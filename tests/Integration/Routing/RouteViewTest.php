<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\NutsAndBolts\Facades\Route;
use Mariner\NutsAndBolts\Facades\View;

test('route view', function () {
    Route::view('route', 'view', ['foo' => 'bar']);

    View::addLocation(dirname(__DIR__, 2).'/Routing/Fixtures');

    expect($this->get('/route')->getContent())->toContain('Test bar');
    expect($this->get('/route')->status())->toBe(200);
});

test('route view with params', function () {
    Route::view('route/{param}/{param2?}', 'view', ['foo' => 'bar']);

    View::addLocation(dirname(__DIR__, 2).'/Routing/Fixtures');

    expect($this->get('/route/value1/value2')->getContent())->toContain('Test bar');
    expect($this->get('/route/value1')->getContent())->toContain('Test bar');

    tap($this->get('/route/value1/value2'), function ($response) {
        expect($response->viewData('param'))->toBe('value1');
        expect($response->baseRequest->route('param'))->toBe('value1');
        expect($response->baseRequest->route('param2'))->toBe('value2');
    });

    tap($this->get('/route/value1/value2'), function ($response) {
        expect($response->viewData('param2'))->toBe('value2');
        expect($response->baseRequest->route('param'))->toBe('value1');
        expect($response->baseRequest->route('param2'))->toBe('value2');
    });
});

test('route view with status', function () {
    Route::view('route', 'view', ['foo' => 'bar'], 418);

    View::addLocation(dirname(__DIR__, 2).'/Routing/Fixtures');

    expect($this->get('/route')->status())->toBe(418);
});

test('route view with headers', function () {
    Route::view('route', 'view', ['foo' => 'bar'], 418, ['Framework' => 'Laravel']);

    View::addLocation(dirname(__DIR__, 2).'/Routing/Fixtures');

    expect($this->get('/route')->headers->get('Framework'))->toBe('Laravel');
});

test('route view overloading status with headers', function () {
    Route::view('route', 'view', ['foo' => 'bar'], ['Framework' => 'Laravel']);

    View::addLocation(dirname(__DIR__, 2).'/Routing/Fixtures');

    expect($this->get('/route')->headers->get('Framework'))->toBe('Laravel');
});
