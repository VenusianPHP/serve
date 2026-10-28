<?php

namespace Venusian\Serve\Tests\Session;

use Voyager\Cache\ArrayStore;
use Voyager\Cache\Repository;
use Mariner\Session\CacheBasedSessionHandler;
use Voyager\NutsAndBolts\DataObjects\Carbon;

beforeEach(function () {
    $this->cache = new Repository(new ArrayStore);
    $this->sessionHandler = new CacheBasedSessionHandler(cache: $this->cache, minutes: 10);
});

afterEach(function () {
    Carbon::setTestNow();
});

test('open', function () {
    $result = $this->sessionHandler->open('path', 'session_name');
    expect($result)->toBeTrue();
});

test('close', function () {
    $result = $this->sessionHandler->close();
    expect($result)->toBeTrue();
});

test('it creates session ids', function () {
    $sessionId = $this->sessionHandler->create_sid();

    expect($sessionId)->toBeString();
    expect($sessionId)->not->toBeEmpty();
});

test('validate id checks cache', function () {
    expect($this->sessionHandler->validateId('session_id'))->toBeFalse();

    $this->sessionHandler->write(sessionId: 'session_id', data: 'session_data');

    expect($this->sessionHandler->validateId('session_id'))->toBeTrue();
});

test('read returns data from cache', function () {
    $this->sessionHandler->write(sessionId: 'session_id', data: 'session_data');

    $data = $this->sessionHandler->read(sessionId: 'session_id');
    expect($data)->toBe('session_data');
});

test('read returns empty string if no data', function () {
    $data = $this->sessionHandler->read(sessionId: 'some_id');
    expect($data)->toBe('');
});

test('write stores data in cache', function () {
    Carbon::setTestNow('2000-01-01 00:00:00');

    $result = $this->sessionHandler->write(sessionId: 'session_id', data: 'session_data');
    expect($result)->toBeTrue();

    // 10 minutes (600 seconds) later, still within the TTL.
    Carbon::setTestNow('2000-01-01 00:09:59');
    expect($this->sessionHandler->read(sessionId: 'session_id'))->toBe('session_data');

    // just past the 10 minute TTL, the entry should have expired.
    Carbon::setTestNow('2000-01-01 00:10:01');
    expect($this->sessionHandler->read(sessionId: 'session_id'))->toBe('');
});

test('destroy removes data from cache', function () {
    $this->sessionHandler->write(sessionId: 'session_id', data: 'session_data');

    $result = $this->sessionHandler->destroy(sessionId: 'session_id');

    expect($result)->toBeTrue();
    expect($this->sessionHandler->read(sessionId: 'session_id'))->toBe('');
});

test('gc returns zero', function () {
    $result = $this->sessionHandler->gc(lifetime: 120);

    expect($result)->toEqual(0);
});

test('get cache returns cache instance', function () {
    $cacheInstance = $this->sessionHandler->getCache();

    expect($cacheInstance)->toBe($this->cache);
});
