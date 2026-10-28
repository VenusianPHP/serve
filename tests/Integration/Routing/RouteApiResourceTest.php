<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\NutsAndBolts\Facades\Route;
use Venusian\Serve\Tests\Routing\Fixtures\ApiResourceTaskController;
use Venusian\Serve\Tests\Routing\Fixtures\ApiResourceTestController;

test('api resource', function () {
    Route::apiResource('tests', ApiResourceTestController::class);

    $response = $this->get('/tests');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m index');

    $response = $this->post('/tests');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m store');

    $response = $this->get('/tests/1');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m show');

    $response = $this->put('/tests/1');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m update');
    $response = $this->patch('/tests/1');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m update');

    $response = $this->delete('/tests/1');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m destroy');
});

test('api resource with only', function () {
    Route::apiResource('tests', ApiResourceTestController::class)->only(['index', 'store']);

    $response = $this->get('/tests');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m index');

    $response = $this->post('/tests');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m store');

    expect($this->get('/tests/1')->getStatusCode())->toEqual(404);
    expect($this->put('/tests/1')->getStatusCode())->toEqual(404);
    expect($this->patch('/tests/1')->getStatusCode())->toEqual(404);
    expect($this->delete('/tests/1')->getStatusCode())->toEqual(404);
});

test('api resources', function () {
    Route::apiResources([
        'tests' => ApiResourceTestController::class,
        'tasks' => ApiResourceTaskController::class,
    ]);

    $response = $this->get('/tests');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m index');

    $response = $this->post('/tests');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m store');

    $response = $this->get('/tests/1');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m show');

    $response = $this->put('/tests/1');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m update');
    $response = $this->patch('/tests/1');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m update');

    $response = $this->delete('/tests/1');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m destroy');

    /////////////////////
    $response = $this->get('/tasks');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m index tasks');

    $response = $this->post('/tasks');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m store tasks');

    $response = $this->get('/tasks/1');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m show tasks');

    $response = $this->put('/tasks/1');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m update tasks');
    $response = $this->patch('/tasks/1');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m update tasks');

    $response = $this->delete('/tasks/1');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('I`m destroy tasks');
});
