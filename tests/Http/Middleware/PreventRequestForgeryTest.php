<?php

namespace Venusian\Serve\Tests\Http\Middleware;

use Voyager\Contracts\Core\FrameworkCore;
use Voyager\Encryption\Encrypter;
use Mariner\Http\Middleware\PreventRequestForgery;
use Mariner\Http\Exceptions\OriginMismatchException;
use Mariner\Http\Request;
use Mariner\Http\Response;
use Mariner\Session\ArraySessionHandler;
use Mariner\Session\Store;
use Mariner\Session\TokenMismatchException;
use Mockery;

afterEach(function () {
    PreventRequestForgery::flushState();
});

test('same origin header passes', function () {
    $middleware = createMiddleware();
    $request = createRequest(['HTTP_SEC_FETCH_SITE' => 'same-origin']);

    $response = $middleware->handle($request, fn () => new Response('OK'));

    expect($response->getContent())->toBe('OK');
});

test('same site header rejected by default', function () {
    $middleware = createMiddleware();
    $request = createRequest(['HTTP_SEC_FETCH_SITE' => 'same-site']);

    $middleware->handle($request, fn () => new Response('OK'));
})->throws(TokenMismatchException::class);

test('same site header passes when allowed', function () {
    PreventRequestForgery::allowSameSite();

    $middleware = createMiddleware();
    $request = createRequest(['HTTP_SEC_FETCH_SITE' => 'same-site']);

    $response = $middleware->handle($request, fn () => new Response('OK'));

    expect($response->getContent())->toBe('OK');
});

test('cross site with valid token passes', function () {
    $middleware = createMiddleware();
    $request = createRequest(['HTTP_SEC_FETCH_SITE' => 'cross-site'], 'test-token');

    $response = $middleware->handle($request, fn () => new Response('OK'));

    expect($response->getContent())->toBe('OK');
});

test('cross site without token fails', function () {
    $middleware = createMiddleware();
    $request = createRequest(['HTTP_SEC_FETCH_SITE' => 'cross-site']);

    $middleware->handle($request, fn () => new Response('OK'));
})->throws(TokenMismatchException::class);

test('missing header without token fails', function () {
    $middleware = createMiddleware();
    $request = createRequest();

    $middleware->handle($request, fn () => new Response('OK'));
})->throws(TokenMismatchException::class);

test('origin only mode rejects cross site', function () {
    PreventRequestForgery::useOriginOnly();

    $middleware = createMiddleware();
    // Even with a valid token, origin-only mode rejects cross-site
    $request = createRequest(['HTTP_SEC_FETCH_SITE' => 'cross-site'], 'test-token');

    $middleware->handle($request, fn () => new Response('OK'));
})->throws(OriginMismatchException::class);

test('origin only mode rejects missing header', function () {
    PreventRequestForgery::useOriginOnly();

    $middleware = createMiddleware();
    $request = createRequest([], 'test-token');

    $middleware->handle($request, fn () => new Response('OK'));
})->throws(OriginMismatchException::class);

test('origin only mode passes same origin', function () {
    PreventRequestForgery::useOriginOnly();

    $middleware = createMiddleware();
    $request = createRequest(['HTTP_SEC_FETCH_SITE' => 'same-origin']);

    $response = $middleware->handle($request, fn () => new Response('OK'));

    expect($response->getContent())->toBe('OK');
});

test('query request without token fails', function () {
    $middleware = createMiddleware();
    $request = createRequest(['HTTP_SEC_FETCH_SITE' => 'cross-site'], method: 'QUERY');

    $middleware->handle($request, fn () => new Response('OK'));
})->throws(TokenMismatchException::class);

test('query request with valid token passes', function () {
    $middleware = createMiddleware();
    $request = createRequest(['HTTP_SEC_FETCH_SITE' => 'cross-site'], 'test-token', 'QUERY');

    $response = $middleware->handle($request, fn () => new Response('OK'));

    expect($response->getContent())->toEqual('OK');
});

function createRequest(array $server = [], ?string $token = null, string $method = 'POST')
{
    $request = Request::create(
        'http://example.com/test',
        $method,
        $token ? ['_token' => $token] : [],
        [],
        [],
        $server
    );
    
    $session = new Store('test', new ArraySessionHandler(10));
    $session->put('_token', 'test-token');
    $request->setMarinerSession($session);
    
    return $request;
}

function createMiddleware()
{
    return new PreventRequestForgeryTestStub(
        Mockery::mock(FrameworkCore::class),
        new Encrypter(str_repeat('a', 16), 'aes-128-cbc')
    );
}

class PreventRequestForgeryTestStub extends PreventRequestForgery
{
    protected $addHttpCookie = false;

    protected function runningUnitTests()
    {
        return false;
    }
}
