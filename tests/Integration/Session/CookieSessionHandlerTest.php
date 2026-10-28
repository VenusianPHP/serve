<?php

namespace Venusian\Serve\Tests\Integration\Session;

use Mariner\NutsAndBolts\Facades\Route;
use Voyager\NutsAndBolts\Str;

beforeEach(function () {
    $app = $this->app;
    $app['config']->set('app.key', Str::random(32));
    $app['config']->set('session.driver', 'cookie');
    $app['config']->set('session.cookie', 'laravel_session');
    $app['config']->set('session.expire_on_close', true);
});

test('cookie session driver cookies can expire on close', function () {
    Route::get('/', fn () => '')->middleware('web');

    $response = $this->get('/');
    $sessionIdCookie = $response->getCookie('laravel_session');
    $sessionValueCookie = $response->getCookie($sessionIdCookie->getValue());

    expect($sessionIdCookie->getExpiresTime())->toEqual(0);
    expect($sessionValueCookie->getExpiresTime())->toEqual(0);
});

test('cookie session inherits request secure state', function () {
    Route::get('/', fn () => '')->middleware('web');

    $unsecureResponse = $this->get('/');
    $unsecureSessionIdCookie = $unsecureResponse->getCookie('laravel_session');
    $unsecureSessionValueCookie = $unsecureResponse->getCookie($unsecureSessionIdCookie->getValue());

    expect($unsecureSessionIdCookie->isSecure())->toBeFalse();
    expect($unsecureSessionValueCookie->isSecure())->toBeFalse();

    $secureResponse = $this->get('https://localhost/');
    $secureSessionIdCookie = $secureResponse->getCookie('laravel_session');
    $secureSessionValueCookie = $secureResponse->getCookie($secureSessionIdCookie->getValue());

    expect($secureSessionIdCookie->isSecure())->toBeTrue();
    expect($secureSessionValueCookie->isSecure())->toBeTrue();
});
