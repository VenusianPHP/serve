<?php

namespace Venusian\Serve\Tests\Integration\Session;

use Mariner\Contracts\Http\ExceptionRenderer;
use Mariner\Http\Response;
use Mariner\Session\NullSessionHandler;
use Mariner\Session\TokenMismatchException;
use Mariner\NutsAndBolts\Facades\Route;
use Mariner\NutsAndBolts\Facades\Session;
use Voyager\NutsAndBolts\Str;

beforeEach(function () {
    $app = $this->app;
    $this->mock(ExceptionRenderer::class, fn ($mock) => $mock->shouldReceive('handle')->andReturn(new Response));

    $app['config']->set('app.key', Str::random(32));
    $app['config']->set('session.driver', 'fake-null');
    $app['config']->set('session.expire_on_close', true);
});

test('session is persisted even if exception is thrown from route', function () {
    $handler = new FakeNullSessionHandler;
    expect($handler->written)->toBeFalse();

    Session::extend('fake-null', function () use ($handler) {
        return $handler;
    });

    Route::get('/', function () {
        throw new TokenMismatchException;
    })->middleware('web');

    $this->get('/');
    expect($handler->written)->toBeTrue();
});

class FakeNullSessionHandler extends NullSessionHandler
{
    public $written = false;

    public function write($sessionId, $data): bool
    {
        $this->written = true;

        return true;
    }
}
