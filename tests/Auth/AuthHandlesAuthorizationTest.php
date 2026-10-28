<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\Access\AuthorizationException;
use Mariner\Auth\Access\HandlesAuthorization;

uses(HandlesAuthorization::class);

test('allow method', function () {
    $response = $this->allow('some message', 'some_code');

    expect($response->allowed())->toBeTrue();
    expect($response->denied())->toBeFalse();
    expect($response->message())->toBe('some message');
    expect($response->code())->toBe('some_code');
});

test('deny method', function () {
    $response = $this->deny('some message', 'some_code');

    expect($response->denied())->toBeTrue();
    expect($response->allowed())->toBeFalse();
    expect($response->message())->toBe('some message');
    expect($response->code())->toBe('some_code');
});

test('deny has null status', function () {
    $class = new class()
    {
        use HandlesAuthorization;

        public function __invoke()
        {
            return $this->deny('xxxx', 321);
        }
    };

    try {
        $class()->authorize();
        $this->fail();
    } catch (AuthorizationException $e) {
        expect($e->hasStatus())->toBeFalse();
        expect($e->status())->toBeNull();
    }
});

test('it can deny with status', function () {
    $class = new class()
    {
        use HandlesAuthorization;

        public function __invoke()
        {
            return $this->denyWithStatus(418);
        }
    };

    try {
        $class()->authorize();
        $this->fail();
    } catch (AuthorizationException $e) {
        expect($e->hasStatus())->toBeTrue();
        expect($e->status())->toBe(418);
        expect($e->getMessage())->toBe('This action is unauthorized.');
        expect($e->getCode())->toBe(0);
    }

    $class = new class()
    {
        use HandlesAuthorization;

        public function __invoke()
        {
            return $this->denyWithStatus(418, 'foo', 3);
        }
    };

    try {
        $class()->authorize();
        $this->fail();
    } catch (AuthorizationException $e) {
        expect($e->hasStatus())->toBeTrue();
        expect($e->status())->toBe(418);
        expect($e->getMessage())->toBe('foo');
        expect($e->getCode())->toBe(3);
    }
});

test('it can deny as not found', function () {
    $class = new class()
    {
        use HandlesAuthorization;

        public function __invoke()
        {
            return $this->denyAsNotFound();
        }
    };

    try {
        $class()->authorize();
        $this->fail();
    } catch (AuthorizationException $e) {
        expect($e->hasStatus())->toBeTrue();
        expect($e->status())->toBe(404);
        expect($e->getMessage())->toBe('This action is unauthorized.');
        expect($e->getCode())->toBe(0);
    }

    $class = new class()
    {
        use HandlesAuthorization;

        public function __invoke()
        {
            return $this->denyAsNotFound('foo', 3);
        }
    };

    try {
        $class()->authorize();
        $this->fail();
    } catch (AuthorizationException $e) {
        expect($e->hasStatus())->toBeTrue();
        expect($e->status())->toBe(404);
        expect($e->getMessage())->toBe('foo');
        expect($e->getCode())->toBe(3);
    }
});
