<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\Access\AuthorizationException;
use Mariner\Auth\Access\Response;

test('allow method', function () {
    $response = Response::allow('some message', 'some_code');

    expect($response->allowed())->toBeTrue();
    expect($response->denied())->toBeFalse();
    expect($response->message())->toBe('some message');
    expect($response->code())->toBe('some_code');
});

test('deny method', function () {
    $response = Response::deny('some message', 'some_code');

    expect($response->denied())->toBeTrue();
    expect($response->allowed())->toBeFalse();
    expect($response->message())->toBe('some message');
    expect($response->code())->toBe('some_code');
});

test('deny method with no message returns null', function () {
    $response = Response::deny();

    expect($response->message())->toBeNull();
});

test('it sets empty status on exception when authorizing', function () {
    try {
        Response::deny('foo', 3)->authorize();
        $this->fail();
    } catch (AuthorizationException $e) {
        expect($e->status())->toBeNull();
        expect($e->hasStatus())->toBeFalse();
        expect($e->response()->message())->toBe('foo');
        expect($e->getMessage())->toBe('foo');
        expect($e->getCode())->toBe(3);
    }
});

test('it sets status on exception when authorizing', function () {
    try {
        Response::deny('foo', 3)->withStatus(418)->authorize();
        $this->fail();
    } catch (AuthorizationException $e) {
        expect($e->status())->toBe(418);
        expect($e->hasStatus())->toBeTrue();
        expect($e->response()->message())->toBe('foo');
        expect($e->getMessage())->toBe('foo');
        expect($e->getCode())->toBe(3);
    }

    try {
        Response::deny('foo', 3)->asNotFound()->authorize();
        $this->fail();
    } catch (AuthorizationException $e) {
        expect($e->status())->toBe(404);
        expect($e->hasStatus())->toBeTrue();
        expect($e->response()->message())->toBe('foo');
        expect($e->getMessage())->toBe('foo');
        expect($e->getCode())->toBe(3);
    }

    try {
        Response::denyWithStatus(444)->authorize();
        $this->fail();
    } catch (AuthorizationException $e) {
        expect($e->status())->toBe(444);
        expect($e->hasStatus())->toBeTrue();
        expect($e->response()->message())->toBeNull();
        expect($e->getMessage())->toBe('This action is unauthorized.');
        expect($e->getCode())->toBe(0);
    }

    try {
        Response::denyWithStatus(444, 'foo', 3)->authorize();
        $this->fail();
    } catch (AuthorizationException $e) {
        expect($e->status())->toBe(444);
        expect($e->hasStatus())->toBeTrue();
        expect($e->response()->message())->toBe('foo');
        expect($e->getMessage())->toBe('foo');
        expect($e->getCode())->toBe(3);
    }

    try {
        Response::denyAsNotFound()->authorize();
        $this->fail();
    } catch (AuthorizationException $e) {
        expect($e->status())->toBe(404);
        expect($e->hasStatus())->toBeTrue();
        expect($e->response()->message())->toBeNull();
        expect($e->getMessage())->toBe('This action is unauthorized.');
        expect($e->getCode())->toBe(0);
    }

    try {
        Response::denyAsNotFound('foo', 3)->authorize();
        $this->fail();
    } catch (AuthorizationException $e) {
        expect($e->status())->toBe(404);
        expect($e->hasStatus())->toBeTrue();
        expect($e->response()->message())->toBe('foo');
        expect($e->getMessage())->toBe('foo');
        expect($e->getCode())->toBe(3);
    }
});

test('authorize method throws authorization exception when response denied', function () {
    $response = Response::deny('Some message.', 'some_code');

    try {
        $response->authorize();
    } catch (AuthorizationException $e) {
        expect($e->getMessage())->toBe('Some message.');
        expect($e->getCode())->toBe('some_code');
        expect($e->response())->toEqual($response);
    }
});

test('authorize method throws authorization exception with default message', function () {
    $response = Response::deny();

    try {
        $response->authorize();
    } catch (AuthorizationException $e) {
        expect($e->getMessage())->toBe('This action is unauthorized.');
    }
});

test('throw if needed doesnt throw authorization exception when response allowed', function () {
    $response = Response::allow('Some message.', 'some_code');

    expect($response->authorize())->toEqual($response);
});

test('casting to string returns message', function () {
    $response = new Response(true, 'some data');
    expect((string) $response)->toBe('some data');

    $response = new Response(false, null);
    expect((string) $response)->toBe('');
});

test('response to array method', function () {
    $response = new Response(false, 'Not allowed.', 'some_code');

    expect($response->toArray())->toEqual([
        'allowed' => false,
        'message' => 'Not allowed.',
        'code' => 'some_code',
    ]);
});
