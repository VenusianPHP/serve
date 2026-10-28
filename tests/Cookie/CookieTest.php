<?php

namespace Venusian\Serve\Tests\Cookie;

use ArgumentCountError;
use Mariner\Cookie\CookieJar;
use InvalidArgumentException;
use ReflectionObject;
use Symfony\Component\HttpFoundation\Cookie;

test('cookies are created with proper options', function () {
    $cookie = getCreator();
    $cookie->setDefaultPathAndDomain('foo', 'bar');
    $c = $cookie->make('color', 'blue', 10, '/path', '/domain', true, false, false, 'lax');
    expect($c->getValue())->toBe('blue');
    expect($c->isHttpOnly())->toBeFalse();
    expect($c->isSecure())->toBeTrue();
    expect($c->getDomain())->toBe('/domain');
    expect($c->getPath())->toBe('/path');
    expect($c->getSameSite())->toBe('lax');

    $c2 = $cookie->forever('color', 'blue', '/path', '/domain', true, false, false, 'strict');
    expect($c2->getValue())->toBe('blue');
    expect($c2->isHttpOnly())->toBeFalse();
    expect($c2->isSecure())->toBeTrue();
    expect($c2->getDomain())->toBe('/domain');
    expect($c2->getPath())->toBe('/path');
    expect($c2->getSameSite())->toBe('strict');

    $c3 = $cookie->forget('color');
    expect($c3->getValue())->toBeNull();
    expect($c3->getExpiresTime() < time())->toBeTrue();
});

test('cookies are created with proper options using default path and domain', function () {
    $cookie = getCreator();
    $cookie->setDefaultPathAndDomain('/path', '/domain', true, 'lax');
    $c = $cookie->make('color', 'blue');
    expect($c->getValue())->toBe('blue');
    expect($c->isSecure())->toBeTrue();
    expect($c->getDomain())->toBe('/domain');
    expect($c->getPath())->toBe('/path');
    expect($c->getSameSite())->toBe('lax');
    expect($c->isHttpOnly())->toBeTrue();
});

test('cookies can set secure option using default path and domain', function () {
    $cookie = getCreator();
    $cookie->setDefaultPathAndDomain('/path', '/domain', true, 'lax');
    $c = $cookie->make('color', 'blue', 10, null, null, false);
    expect($c->getValue())->toBe('blue');
    expect($c->isSecure())->toBeFalse();
    expect($c->getDomain())->toBe('/domain');
    expect($c->getPath())->toBe('/path');
    expect($c->getSameSite())->toBe('lax');
});

test('queued cookies without name', function () {

    $cookie = getCreator();
    $cookie->queue($cookie->make('', 'bar'));
})->throws(InvalidArgumentException::class);

test('queued cookies with invalid parameter', function () {

    $cookie = getCreator();
    $cookie->queue('invalidCookie');
})->throws(ArgumentCountError::class);

test('queued cookies with handling empty values', function () {
    $cookie = getCreator();
    $cookie->queue($cookie->make('foo', ''));
    expect($cookie->hasQueued('foo'))->toBeTrue();
    expect($cookie->queued('foo')->getValue())->toBe('');
});

test('queued cookies with repeated value', function () {
    $cookie = getCreator();
    $cookie->queue($cookie->make('foo', 'newBar'));
    expect($cookie->hasQueued('foo'))->toBeTrue();
    expect($cookie->queued('foo')->getValue())->toBe('newBar');
    $cookie->queue('invalidCookie');
})->throws(ArgumentCountError::class);

test('queued cookies', function () {
    $cookie = getCreator();
    expect($cookie->getQueuedCookies())->toBeEmpty();
    expect($cookie->hasQueued('foo'))->toBeFalse();
    $cookie->queue($cookie->make('foo', 'bar'));
    expect($cookie->hasQueued('foo'))->toBeTrue();
    expect($cookie->queued('foo'))->toBeInstanceOf(Cookie::class);
    $cookie->queue('qu', 'ux');
    expect($cookie->hasQueued('qu'))->toBeTrue();
    expect($cookie->queued('qu'))->toBeInstanceOf(Cookie::class);
});

test('queued with path', function () {
    $cookieJar = getCreator();
    $cookieOne = $cookieJar->make('foo', 'bar', 0, '/path');
    $cookieTwo = $cookieJar->make('foo', 'rab', 0, '/');
    $cookieJar->queue($cookieOne);
    $cookieJar->queue($cookieTwo);
    expect($cookieJar->queued('foo', null, '/path'))->toEqual($cookieOne);
    expect($cookieJar->queued('foo', null, '/'))->toEqual($cookieTwo);
});

test('queued without path', function () {
    $cookieJar = getCreator();
    $cookieOne = $cookieJar->make('foo', 'bar', 0, '/path');
    $cookieTwo = $cookieJar->make('foo', 'rab', 0, '/');
    $cookieJar->queue($cookieOne);
    $cookieJar->queue($cookieTwo);
    expect($cookieJar->queued('foo'))->toEqual($cookieTwo);
});

test('queued returns the default when the cookie is not queued', function () {
    $cookieJar = getCreator();
    $fallback = $cookieJar->make('fallback', 'bar');
    $cookieJar->queue($cookieJar->make('foo', 'bar', 0, '/path'));

    expect($cookieJar->queued('nonexistent'))->toBeNull();
    expect($cookieJar->queued('nonexistent', $fallback))->toBe($fallback);
    expect($cookieJar->queued('nonexistent', 'bar'))->toBe('bar');
    expect($cookieJar->queued('nonexistent', fn () => 'bar'))->toBe('bar');
    expect($cookieJar->queued('nonexistent', $fallback, '/path'))->toBe($fallback);
    expect($cookieJar->queued('foo', $fallback, '/wrongPath'))->toBe($fallback);
});

test('queued resolves adefault closure only once', function () {
    $cookieJar = getCreator();
    $invocations = 0;
    $default = function () use (&$invocations) {
        $invocations++;

        return 'bar';
    };

    expect($cookieJar->queued('nonexistent', $default, '/path'))->toBe('bar');
    expect($invocations)->toBe(1);
});

test('queued does not resolve cookie names using dot notation', function () {
    $cookieJar = getCreator();
    $cookieJar->queue($cookieJar->make('foo', 'bar', 0, 'path'));

    expect($cookieJar->queued('foo.path'))->toBeNull();
    expect($cookieJar->queued('foo.path', 'fallback'))->toBe('fallback');
    expect($cookieJar->hasQueued('foo.path'))->toBeFalse();
});

test('queued finds cookie names containing dots', function () {
    $cookieJar = getCreator();
    $cookie = $cookieJar->make('foo.path', 'bar');
    $cookieJar->queue($cookie);

    expect($cookieJar->queued('foo.path'))->toBe($cookie);
    expect($cookieJar->hasQueued('foo.path'))->toBeTrue();
});

test('has queued', function () {
    $cookieJar = getCreator();
    // test empty queue
    expect($cookieJar->hasQueued('foo'))->toBeFalse();

    $cookie = $cookieJar->make('foo', 'bar');
    $cookieJar->queue($cookie);
    expect($cookieJar->hasQueued('foo'))->toBeTrue();
    expect($cookieJar->hasQueued('nonexistent'))->toBeFalse();
});

test('has queued with path', function () {
    $cookieJar = getCreator();
    $cookieOne = $cookieJar->make('foo', 'bar', 0, '/path');
    $cookieTwo = $cookieJar->make('foo', 'rab', 0, '/');
    $cookieJar->queue($cookieOne);
    $cookieJar->queue($cookieTwo);
    expect($cookieJar->hasQueued('foo', '/path'))->toBeTrue();
    expect($cookieJar->hasQueued('foo', '/'))->toBeTrue();
    expect($cookieJar->hasQueued('foo', '/wrongPath'))->toBeFalse();
});

test('expire', function () {
    $cookieJar = getCreator();
    expect($cookieJar->getQueuedCookies())->toHaveCount(0);

    $cookieJar->expire('foobar', '/path', '/domain');

    $cookie = $cookieJar->queued('foobar');
    expect($cookie->getName())->toBe('foobar');
    expect($cookie->getValue())->toEqual(null);
    expect($cookie->getPath())->toBe('/path');
    expect($cookie->getDomain())->toBe('/domain');
    expect($cookie->getExpiresTime() < time())->toBeTrue();
    expect($cookieJar->getQueuedCookies())->toHaveCount(1);
});

test('unqueue', function () {
    $cookie = getCreator();

    $cookie->unqueue('nonexistent');
    expect($cookie->getQueuedCookies())->toBeEmpty();

    $cookie->queue($cookie->make('foo', 'bar'));
    $cookie->unqueue('foo');
    expect($cookie->getQueuedCookies())->toBeEmpty();
});

test('unqueue multiple cookies', function () {
    $cookie = getCreator();
    $cookie->queue($cookie->make('foo', 'bar'));
    $cookie->queue($cookie->make('baz', 'qux'));
    $cookie->unqueue('foo');
    expect($cookie->hasQueued('baz'))->toBeTrue();
    expect($cookie->hasQueued('foo'))->toBeFalse();
});

test('unqueue with path', function () {
    $cookieJar = getCreator();
    $cookieOne = $cookieJar->make('foo', 'bar', 0, '/path');
    $cookieTwo = $cookieJar->make('foo', 'rab', 0, '/');
    $cookieJar->queue($cookieOne);
    $cookieJar->queue($cookieTwo);
    $cookieJar->unqueue('foo', '/path');
    expect(getQueuedPropertyValue($cookieJar))->toEqual(['foo' => ['/' => $cookieTwo]]);
});

test('unqueue only cookie for name', function () {
    $cookieJar = getCreator();
    $cookie = $cookieJar->make('foo', 'bar', 0, '/path');
    $cookieJar->queue($cookie);
    $cookieJar->unqueue('foo', '/path');
    expect(getQueuedPropertyValue($cookieJar))->toBeEmpty();
});

test('cookie jar is macroable', function () {
    $cookie = getCreator();
    $cookie->macro('foo', function () {
        return 'bar';
    });
    expect($cookie->foo())->toBe('bar');
});

test('queue cookie', function () {
    $cookieJar = getCreator();
    $cookie = $cookieJar->make('foo', 'bar', 0, '/path');
    $cookieJar->queue($cookie);
    expect(getQueuedPropertyValue($cookieJar))->toEqual(['foo' => ['/path' => $cookie]]);
});

test('queue with creating new cookie', function () {
    $cookieJar = getCreator();
    $cookieJar->queue('foo', 'bar', 0, '/path');
    expect(getQueuedPropertyValue($cookieJar))->toEqual(['foo' => ['/path' => new Cookie('foo', 'bar', 0, '/path')]]);
});

test('get queued cookies', function () {
    $cookieJar = getCreator();
    $cookieOne = $cookieJar->make('foo', 'bar', 0, '/path');
    $cookieTwo = $cookieJar->make('foo', 'rab', 0, '/');
    $cookieThree = $cookieJar->make('oof', 'bar', 0, '/path');
    $cookieJar->queue($cookieOne);
    $cookieJar->queue($cookieTwo);
    $cookieJar->queue($cookieThree);
    expect($cookieJar->getQueuedCookies())->toEqual([$cookieOne, $cookieTwo, $cookieThree]);
});

test('flush queued cookies', function () {
    $cookieJar = getCreator();
    $cookieJar->queue($cookieJar->make('foo', 'bar', 0, '/path'));
    $cookieJar->queue($cookieJar->make('foo', 'rab', 0, '/'));
    expect($cookieJar->getQueuedCookies())->toHaveCount(2);

    $cookieJar->flushQueuedCookies();
    expect($cookieJar->getQueuedCookies())->toBeEmpty();
});

function getCreator()
{
    return new CookieJar;
}

function getQueuedPropertyValue(CookieJar $cookieJar)
{
    $property = (new ReflectionObject($cookieJar))->getProperty('localQueue');

    return $property->getValue($cookieJar)->cookies;
}
