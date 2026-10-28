<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\Contracts\NutsAndBolts\Responsable;
use Mariner\NutsAndBolts\Facades\Route;

test('responsable objects are rendered', function () {
    Route::get('/responsable', function () {
        return new TestResponsableResponse;
    });

    $response = $this->get('/responsable');

    expect($response->status())->toEqual(201);
    expect($response->headers->get('X-Test-Header'))->toBe('Taylor');
    expect($response->getContent())->toBe('hello world');
});

class TestResponsableResponse implements Responsable
{
    public function toResponse($request)
    {
        return response('hello world', 201, ['X-Test-Header' => 'Taylor']);
    }
}
