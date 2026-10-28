<?php

namespace Venusian\Serve\Tests\Session\Middleware;

use BadMethodCallException;
use Mariner\Auth\AuthenticationException;
use Mariner\Contracts\Auth\Factory as AuthFactory;
use Mariner\Http\Request;
use Mariner\Session\ArraySessionHandler;
use Mariner\Session\Middleware\AuthenticateSession;
use Mariner\Session\Store;
use Mockery;

test('handle without session', function () {
    $request = new Request;
    $next = fn () => 'next-1';

    $authFactory = Mockery::mock(AuthFactory::class);
    $authFactory->shouldReceive('viaRemember')->never();

    $middleware = new AuthenticateSession($authFactory);
    $response = $middleware->handle($request, $next);
    expect($response)->toBe('next-1');
});

test('handle with session without request user', function () {
    $request = new Request;

    // set session:
    $request->setMarinerSession(new Store('name', new ArraySessionHandler(1)));

    $authFactory = Mockery::mock(AuthFactory::class);
    $authFactory->shouldReceive('viaRemember')->never();

    $next = fn () => 'next-2';
    $middleware = new AuthenticateSession($authFactory);
    $response = $middleware->handle($request, $next);
    expect($response)->toBe('next-2');
});

test('handle with session without auth password', function () {
    $user = new class
    {
        public function getAuthPassword()
        {
            return null;
        }
    };

    $request = new Request;

    // set session:
    $request->setMarinerSession(new Store('name', new ArraySessionHandler(1)));
    // set a password-less user:
    $request->setUserResolver(fn () => $user);

    $authFactory = Mockery::mock(AuthFactory::class);
    $authFactory->shouldReceive('viaRemember')->never();

    $next = fn () => 'next-3';
    $middleware = new AuthenticateSession($authFactory);
    $response = $middleware->handle($request, $next);

    expect($response)->toBe('next-3');
});

test('handle with session with user auth password on request via remember false', function () {
    $user = new class
    {
        public function getAuthPassword()
        {
            return 'my-pass-(*&^%$#!@';
        }
    };

    $request = new Request;
    $request->setUserResolver(fn () => $user);

    $session = new Store('name', new ArraySessionHandler(1));
    $request->setMarinerSession($session);

    $authFactory = Mockery::mock(AuthFactory::class);
    $authFactory->expects('viaRemember')->andReturn(false);
    $authFactory->expects('getDefaultDriver')->times(3)->andReturn('web');
    $authFactory->expects('user')->andReturn(null);
    // expected MAC for current password when storing in session:
    $authFactory->expects('hashPasswordForCookie')->times(2)->with('my-pass-(*&^%$#!@')->andReturn('mac:my-pass-(*&^%$#!@');

    $middleware = new AuthenticateSession($authFactory);
    $response = $middleware->handle($request, fn () => 'next-4');

    expect($session->get('password_hash_web'))->toBe('mac:my-pass-(*&^%$#!@');
    expect($response)->toBe('next-4');
});

test('handle with invalid password hash', function () {
    $user = new class
    {
        public function getAuthPassword()
        {
            return 'my-pass-(*&^%$#!@';
        }
    };

    $request = new Request(cookies: ['recaller-name' => 'a|b|invalid-mac']);
    $request->setUserResolver(fn () => $user);

    $session = new Store('name', new ArraySessionHandler(1));
    $session->put('a', '1');
    $session->put('b', '2');
    // set session:
    $request->setMarinerSession($session);

    $authFactory = Mockery::mock(AuthFactory::class);
    $authFactory->expects('viaRemember')->andReturn(true);
    $authFactory->expects('getRecallerName')->andReturn('recaller-name');
    $authFactory->expects('logoutCurrentDevice')->andReturn(null);
    $authFactory->expects('getDefaultDriver')->andReturn('web');
    // expected MAC for current password (won't match cookie):
    $authFactory->expects('hashPasswordForCookie')->with('my-pass-(*&^%$#!@')->andReturn('mac:my-pass-(*&^%$#!@');

    expect($session->get('a'))->not->toBeNull();
    expect($session->get('b'))->not->toBeNull();
    AuthenticateSession::redirectUsing(fn ($request) => 'i-wanna-go-home');

    // act:
    $middleware = new AuthenticateSession($authFactory);

    $message = '';
    try {
        $middleware->handle($request, fn () => 'next-7');
    } catch (AuthenticationException $e) {
        $message = $e->getMessage();
        expect($e->redirectTo($request))->toBe('i-wanna-go-home');
    }
    expect($message)->toBe('Unauthenticated.');

    // ensure session is flushed:
    expect($session->get('a'))->toBeNull();
    expect($session->get('b'))->toBeNull();
});

test('handle with invalid incookie password hash via remember true', function () {
    $user = new class
    {
        public function getAuthPassword()
        {
            return 'my-pass-(*&^%$#!@';
        }
    };

    $request = new Request(cookies: ['recaller-name' => 'a|b|invalid-mac']);
    $request->setUserResolver(fn () => $user);

    $session = new Store('name', new ArraySessionHandler(1));
    $session->put('a', '1');
    $session->put('b', '2');
    // set session:
    $request->setMarinerSession($session);

    $authFactory = Mockery::mock(AuthFactory::class);
    $authFactory->expects('viaRemember')->andReturn(true);
    $authFactory->expects('getRecallerName')->andReturn('recaller-name');
    $authFactory->expects('logoutCurrentDevice');
    $authFactory->expects('getDefaultDriver')->andReturn('web');
    // expected MAC for current password (won't match cookie):
    $authFactory->expects('hashPasswordForCookie')->with('my-pass-(*&^%$#!@')->andReturn('mac:my-pass-(*&^%$#!@');

    $middleware = new AuthenticateSession($authFactory);
    // act:
    try {
        $message = '';
        $middleware->handle($request, fn () => 'next-6');
    } catch (AuthenticationException $e) {
        $message = $e->getMessage();
    }
    expect($message)->toBe('Unauthenticated.');

    // ensure session is flushed
    expect($session->get('password_hash_web'))->toBeNull();
    expect($session->get('a'))->toBeNull();
    expect($session->get('b'))->toBeNull();
});

test('handle with valid incookie invalid insession hash via remember true', function () {
    $user = new class
    {
        public function getAuthPassword()
        {
            return 'my-pass-(*&^%$#!@';
        }
    };

    $request = new Request(cookies: ['recaller-name' => 'a|b|mac:my-pass-(*&^%$#!@']);
    $request->setUserResolver(fn () => $user);

    $session = new Store('name', new ArraySessionHandler(1));
    $session->put('a', '1');
    $session->put('b', '2');
    $session->put('password_hash_web', 'invalid-password');
    // set session on the request:
    $request->setMarinerSession($session);

    $authFactory = Mockery::mock(AuthFactory::class);
    $authFactory->expects('viaRemember')->andReturn(true);
    $authFactory->expects('getRecallerName')->andReturn('recaller-name');
    $authFactory->expects('logoutCurrentDevice')->andReturn(null);
    $authFactory->expects('getDefaultDriver')->times(3)->andReturn('web');
    // expected MAC for current password (matches cookie but not session):
    $authFactory->expects('hashPasswordForCookie')->times(2)->with('my-pass-(*&^%$#!@')->andReturn('mac:my-pass-(*&^%$#!@');

    // act:
    $middleware = new AuthenticateSession($authFactory);
    try {
        $message = '';
        $middleware->handle($request, fn () => 'next-7');
    } catch (AuthenticationException $e) {
        $message = $e->getMessage();
    }
    expect($message)->toBe('Unauthenticated.');

    // ensure session is flushed:
    expect($session->get('password_hash_web'))->toBeNull();
    expect($session->get('a'))->toBeNull();
    expect($session->get('b'))->toBeNull();
});

test('handle with valid password in session cookie is empty guard has user', function () {
    $user = new class
    {
        public function getAuthPassword()
        {
            return 'my-pass-(*&^%$#!@';
        }
    };

    $request = new Request(cookies: ['recaller-name' => 'a|b']);
    $request->setUserResolver(fn () => $user);

    $session = new Store('name', new ArraySessionHandler(1));
    $session->put('a', '1');
    $session->put('b', '2');
    $session->put('password_hash_web', 'mac:my-pass-(*&^%$#!@');
    // set session on the request:
    $request->setMarinerSession($session);

    $authFactory = Mockery::mock(AuthFactory::class);
    $authFactory->expects('viaRemember')->andReturn(false);
    $authFactory->shouldReceive('getRecallerName')->never();
    $authFactory->shouldReceive('logoutCurrentDevice')->never();
    $authFactory->expects('getDefaultDriver')->times(3)->andReturn('web');
    $authFactory->expects('user')->andReturn($user);
    // expected MAC for current password:
    $authFactory->expects('hashPasswordForCookie')->times(2)->with('my-pass-(*&^%$#!@')->andReturn('mac:my-pass-(*&^%$#!@');

    // act:
    $middleware = new AuthenticateSession($authFactory);
    $response = $middleware->handle($request, fn () => 'next-8');

    expect($response)->toBe('next-8');
    // ensure session is flushed:
    expect($session->get('password_hash_web'))->toBe('mac:my-pass-(*&^%$#!@');
    expect($session->get('a'))->toBe('1');
    expect($session->get('b'))->toBe('2');
});

test('handle with old format cookie for backward compatibility', function () {
    $user = new class
    {
        public function getAuthPassword()
        {
            return 'my-pass-(*&^%$#!@';
        }
    };

    // Cookie contains OLD format (raw password hash, not HMAC)
    $request = new Request(cookies: ['recaller-name' => 'a|b|my-pass-(*&^%$#!@']);
    $request->setUserResolver(fn () => $user);

    $session = new Store('name', new ArraySessionHandler(1));
    $session->put('a', '1');
    $session->put('b', '2');
    // Session also contains old format for this test
    $session->put('password_hash_web', 'my-pass-(*&^%$#!@');
    $request->setMarinerSession($session);

    $authFactory = Mockery::mock(AuthFactory::class);
    $authFactory->expects('viaRemember')->andReturn(true);
    $authFactory->expects('getRecallerName')->andReturn('recaller-name');
    $authFactory->expects('getDefaultDriver')->times(3)->andReturn('web');
    $authFactory->expects('user')->andReturn($user);
    // The HMAC won't match the old format, but fallback to raw hash should work
    $authFactory->expects('hashPasswordForCookie')->times(3)->with('my-pass-(*&^%$#!@')->andReturn('mac:my-pass-(*&^%$#!@');

    $middleware = new AuthenticateSession($authFactory);
    $response = $middleware->handle($request, fn () => 'next-9');

    // Should succeed because of backward compatibility fallback
    expect($response)->toBe('next-9');
    // Session should be updated to new format (HMAC)
    expect($session->get('password_hash_web'))->toBe('mac:my-pass-(*&^%$#!@');
    expect($session->get('a'))->toBe('1');
    expect($session->get('b'))->toBe('2');
});

test('handle with old format cookie and legacy guard', function () {
    $user = new class
    {
        public function getAuthPassword()
        {
            return 'my-pass-(*&^%$#!@';
        }
    };

    // Cookie contains OLD format (raw password hash, not HMAC)
    $request = new Request(cookies: ['recaller-name' => 'a|b|my-pass-(*&^%$#!@']);
    $request->setUserResolver(fn () => $user);

    $session = new Store('name', new ArraySessionHandler(1));
    $session->put('a', '1');
    $session->put('b', '2');
    // Session also contains old format for this test
    $session->put('password_hash_web', 'my-pass-(*&^%$#!@');
    $request->setMarinerSession($session);

    $authFactory = Mockery::mock(AuthFactory::class);
    $authFactory->expects('viaRemember')->andReturn(true);
    $authFactory->expects('getRecallerName')->andReturn('recaller-name');
    $authFactory->expects('getDefaultDriver')->times(3)->andReturn('web');
    $authFactory->expects('user')->andReturn($user);
    // For legacy guards without hashPasswordForCookie method, we use fallback to raw hash
    $authFactory->expects('hashPasswordForCookie')->times(3)->andThrowExceptions([new BadMethodCallException]);

    $middleware = new AuthenticateSession($authFactory);
    $response = $middleware->handle($request, fn () => 'next-9');

    // Should succeed because of backward compatibility fallback
    expect($response)->toBe('next-9');
    // Session should stay intact
    expect($session->get('password_hash_web'))->toBe('my-pass-(*&^%$#!@');
    expect($session->get('a'))->toBe('1');
    expect($session->get('b'))->toBe('2');
});
