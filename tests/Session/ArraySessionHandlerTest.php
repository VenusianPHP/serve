<?php

namespace Venusian\Serve\Tests\Session;

use Mariner\Session\ArraySessionHandler;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use SessionHandlerInterface;

test('it implements the session handler interface', function () {
    expect(new ArraySessionHandler(10))->toBeInstanceOf(SessionHandlerInterface::class);
});

test('it creates session ids', function () {
    $sessionId = (new ArraySessionHandler(10))->create_sid();

    expect($sessionId)->toBeString();
    expect($sessionId)->not->toBeEmpty();
});

test('it initializes the session', function () {
    $handler = new ArraySessionHandler(10);

    expect($handler->open('', ''))->toBeTrue();
});

test('it validates session ids', function () {
    $handler = new ArraySessionHandler(10);

    expect($handler->validateId('foo'))->toBeFalse();

    $handler->write('foo', 'bar');

    expect($handler->validateId('foo'))->toBeTrue();
});

test('it closes the session', function () {
    $handler = new ArraySessionHandler(10);

    expect($handler->close())->toBeTrue();
});

test('it reads data from the session', function () {
    $handler = new ArraySessionHandler(10);

    $handler->write('foo', 'bar');

    expect($handler->read('foo'))->toBe('bar');
});

test('it reads data from an almost expired session', function () {
    $handler = new ArraySessionHandler(10);

    Carbon::setTestNow($now = Carbon::now());
    $handler->write('foo', 'bar');

    Carbon::setTestNow($now->addMinutes(10));
    expect($handler->read('foo'))->toBe('bar');
});

test('it reads data from an expired session', function () {
    $handler = new ArraySessionHandler(10);

    Carbon::setTestNow($now = Carbon::now());
    $handler->write('foo', 'bar');

    Carbon::setTestNow($now->addMinutes(10)->addSecond());
    expect($handler->read('foo'))->toBe('');
});

test('it reads data from a non existing session', function () {
    $handler = new ArraySessionHandler(10);

    expect($handler->read('foo'))->toBe('');
});

test('it writes session data', function () {
    $handler = new ArraySessionHandler(10);

    expect($handler->write('foo', 'bar'))->toBeTrue();
    expect($handler->read('foo'))->toBe('bar');

    expect($handler->write('foo', 'baz'))->toBeTrue();
    expect($handler->read('foo'))->toBe('baz');
});

test('it destroys a session', function () {
    $handler = new ArraySessionHandler(10);

    expect($handler->destroy('foo'))->toBeTrue();

    $handler->write('foo', 'bar');

    expect($handler->destroy('foo'))->toBeTrue();
    expect($handler->read('foo'))->toBe('');
});

test('it cleans up old sessions', function () {
    $handler = new ArraySessionHandler(10);

    expect($handler->gc(300))->toBe(0);

    Carbon::setTestNow($now = Carbon::now());
    $handler->write('foo', 'bar');
    expect($handler->gc(300))->toBe(0);
    expect($handler->read('foo'))->toBe('bar');

    Carbon::setTestNow($now->addSecond());

    $handler->write('baz', 'qux');

    Carbon::setTestNow($now->addMinutes(5));

    expect($handler->gc(300))->toBe(1);
    expect($handler->read('foo'))->toBe('');
    expect($handler->read('baz'))->toBe('qux');
});
