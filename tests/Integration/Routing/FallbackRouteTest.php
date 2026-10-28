<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\NutsAndBolts\Facades\Route;

test('basic fallback', function () {
    Route::fallback(function () {
        return response('fallback', 404);
    });

    Route::get('one', function () {
        return 'one';
    });

    expect($this->get('/one')->getContent())->toContain('one');
    expect($this->get('/non-existing')->getContent())->toContain('fallback');
    expect($this->get('/non-existing')->getStatusCode())->toEqual(404);
});

test('fallback with prefix', function () {
    Route::group(['prefix' => 'prefix'], function () {
        Route::fallback(function () {
            return response('fallback', 404);
        });

        Route::get('one', function () {
            return 'one';
        });
    });

    expect($this->get('/prefix/one')->getContent())->toContain('one');
    expect($this->get('/prefix/non-existing')->getContent())->toContain('fallback');
    expect($this->get('/prefix/non-existing/with/multiple/segments')->getContent())->toContain('fallback');
    expect($this->get('/non-existing')->getContent())->toContain('Not Found');
});

test('fallback with wildcards', function () {
    Route::fallback(function () {
        return response('fallback', 404);
    });

    Route::get('one', function () {
        return 'one';
    });

    Route::get('{any}', function () {
        return 'wildcard';
    })->where('any', '.*');

    expect($this->get('/one')->getContent())->toContain('one');

    tap($this->get('/non-existing'), function ($response) {
        expect($response->getContent())->toContain('wildcard');
        expect($response->getStatusCode())->toEqual(200);

        expect($response->baseRequest->route('any'))->toBe('non-existing');
    });
});

test('no routes', function () {
    Route::fallback(function () {
        return response('fallback', 404);
    });

    expect($this->get('/non-existing')->getContent())->toContain('fallback');
    expect($this->get('/non-existing')->getStatusCode())->toEqual(404);
});

test('respond with named fallback route', function () {
    Route::fallback(function () {
        return response('fallback', 404);
    })->name('testFallbackRoute');

    Route::get('one', function () {
        return Route::respondWithRoute('testFallbackRoute');
    });

    expect($this->get('/non-existing')->getContent())->toContain('fallback');
    expect($this->get('/one')->getContent())->toContain('fallback');
});

test('no fallbacks', function () {
    Route::get('one', function () {
        return 'one';
    });

    expect($this->get('/one')->getContent())->toContain('one');
    expect($this->get('/one')->getStatusCode())->toEqual(200);
});
