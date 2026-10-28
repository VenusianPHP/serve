<?php

namespace Venusian\Serve\Tests\Integration\View;

use Exception;
use Mariner\Http\Response;
use Mariner\NutsAndBolts\Facades\Route;
use Mariner\NutsAndBolts\Facades\View;

beforeEach(function () {
    $app = $this->app;
    $app['config']->set('view.paths', [__DIR__.'/Fixtures/templates']);
});

test('render method of exception thrown in view gets handled', function () {
    Route::get('/', function () {
        return View::make('renderable-exception');
    });

    $response = $this->get('/');

    $response->assertSee('This is a renderable exception.');
});

class RenderableException extends Exception
{
    public function render($request)
    {
        return new Response('This is a renderable exception.');
    }
}
