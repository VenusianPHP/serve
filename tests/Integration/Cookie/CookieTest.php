<?php

namespace Venusian\Serve\Tests\Integration\Cookie;

use Mariner\Contracts\Http\ExceptionRenderer;
use Mariner\Http\Response;
use Mariner\Session\NullSessionHandler;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mariner\NutsAndBolts\Facades\Route;
use Mariner\NutsAndBolts\Facades\Session;
use Voyager\NutsAndBolts\Str;

beforeEach(function () {
    $app = $this->app;
    $this->mock(ExceptionRenderer::class, fn ($mock) => $mock->shouldReceive('handle')->andReturn(new Response));

    $app['config']->set('app.key', Str::random(32));
    $app['config']->set('session.driver', 'fake-null');

    Session::extend('fake-null', function () {
        return new NullSessionHandler;
    });
});

test('cookie is sent back with proper expire time when should expire on close', function () {
    $this->app['config']->set('session.expire_on_close', true);

    Route::get('/', function () {
        return 'hello world';
    })->middleware('web');

    $response = $this->get('/');
    expect($response->headers->getCookies())->toHaveCount(2);
    expect($response->headers->getCookies()[1]->getExpiresTime())->toEqual(0);
});

test('cookie is sent back with proper expire time with respect to lifetime', function () {
    $this->app['config']->set('session.expire_on_close', false);
    $this->app['config']->set('session.lifetime', 1);

    Route::get('/', function () {
        return 'hello world';
    })->middleware('web');

    Carbon::setTestNow($now = Carbon::now());
    $response = $this->get('/');
    expect($response->headers->getCookies())->toHaveCount(2);
    expect($response->headers->getCookies()[1]->getExpiresTime())->toEqual($now->addMinute()->getTimestamp());
});
