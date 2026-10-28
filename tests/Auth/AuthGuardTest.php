<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\AuthenticationException;
use Mariner\Auth\Events\Attempting;
use Mariner\Auth\Events\Authenticated;
use Mariner\Auth\Events\CurrentDeviceLogout;
use Mariner\Auth\Events\Failed;
use Mariner\Auth\Events\Login;
use Mariner\Auth\Events\Logout;
use Mariner\Auth\Events\Validated;
use Mariner\Auth\GenericUser;
use Mariner\Auth\SessionGuard;
use Mariner\Contracts\Auth\Authenticatable;
use Mariner\Contracts\Auth\UserProvider;
use Voyager\Contracts\Encryption\Encrypter;
use Mariner\Contracts\Session\Session;
use Mariner\Cookie\CookieJar;
use Voyager\Signals\SignalDispatcher;
use Mariner\Session\ArraySessionHandler;
use Mariner\Session\Store;
use Venusian\Serve\Tests\Fixtures\SignalRecorder;
use Mariner\NutsAndBolts\Timebox;
use Mockery;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\UnauthorizedHttpException;

test('basic returns null on valid attempt', function () {
    [$guard, $provider, $session] = getRealGuard();

    $user = new GenericUser(['id' => 10, 'password' => 'secret']);
    $credentials = ['email' => 'foo@bar.com', 'password' => 'secret'];
    $provider->expects('retrieveByCredentials')->with($credentials)->andReturn($user);
    $provider->expects('validateCredentials')->with($user, $credentials)->andReturn(true);
    $provider->expects('rehashPasswordIfRequired')->with($user, $credentials);
    $guard->setRequest(Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret']));

    expect($guard->basic('email'))->toBeNull();
    expect($guard->user())->toBe($user);
    expect($session->get($guard->getName()))->toBe(10);
});

test('basic returns null when already logged in', function () {
    [$guard, $provider] = getRealGuard();

    $user = new GenericUser(['id' => 10, 'password' => 'secret']);
    $guard->setUser($user);
    $provider->shouldNotReceive('retrieveByCredentials');
    $guard->setRequest(Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret']));

    expect($guard->basic('email'))->toBeNull();
    expect($guard->user())->toBe($user);
});

test('basic returns response on failure', function () {

    [$guard, $provider] = getRealGuard();

    $user = new GenericUser(['id' => 10, 'password' => 'secret']);
    $credentials = ['email' => 'foo@bar.com', 'password' => 'secret'];
    $provider->expects('retrieveByCredentials')->with($credentials)->andReturn($user);
    $provider->expects('validateCredentials')->with($user, $credentials)->andReturn(false);
    $provider->shouldNotReceive('rehashPasswordIfRequired');
    $guard->setRequest(Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret']));

    $guard->basic('email');
})->throws(UnauthorizedHttpException::class);

test('basic with extra conditions', function () {
    [$guard, $provider, $session] = getRealGuard();

    $user = new GenericUser(['id' => 10, 'password' => 'secret']);
    $credentials = ['email' => 'foo@bar.com', 'password' => 'secret', 'active' => 1];
    $provider->expects('retrieveByCredentials')->with($credentials)->andReturn($user);
    $provider->expects('validateCredentials')->with($user, $credentials)->andReturn(true);
    $provider->expects('rehashPasswordIfRequired')->with($user, $credentials);
    $guard->setRequest(Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret']));

    expect($guard->basic('email', ['active' => 1]))->toBeNull();
    expect($guard->user())->toBe($user);
    expect($session->get($guard->getName()))->toBe(10);
});

test('basic with extra array conditions', function () {
    [$guard, $provider, $session] = getRealGuard();

    $user = new GenericUser(['id' => 10, 'password' => 'secret']);
    $credentials = ['email' => 'foo@bar.com', 'password' => 'secret', 'active' => 1, 'type' => [1, 2, 3]];
    $provider->expects('retrieveByCredentials')->with($credentials)->andReturn($user);
    $provider->expects('validateCredentials')->with($user, $credentials)->andReturn(true);
    $provider->expects('rehashPasswordIfRequired')->with($user, $credentials);
    $guard->setRequest(Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo@bar.com', 'PHP_AUTH_PW' => 'secret']));

    expect($guard->basic('email', ['active' => 1, 'type' => [1, 2, 3]]))->toBeNull();
    expect($guard->user())->toBe($user);
    expect($session->get($guard->getName()))->toBe(10);
});

test('attempt calls retrieve by credentials', function () {
    $guard = getGuard();
    $events = new SignalRecorder;
    $guard->setDispatcher($events->dispatcher());
    $timebox = $guard->getTimebox();
    $timebox->expects('call')->andReturnUsing(function ($callback) use ($timebox) {
        return $callback($timebox);
    });
    $guard->getProvider()->expects('retrieveByCredentials')->with(['foo']);
    $guard->getProvider()->shouldNotReceive('rehashPasswordIfRequired');
    $guard->attempt(['foo']);

    $events->assertDispatchedOnce(Attempting::class);
    $events->assertDispatchedOnce(Failed::class);
    $events->assertNotDispatched(Validated::class);
});

test('attempt returns user interface', function () {
    [$session, $provider, $request, $cookie, $timebox] = getMocks();
    $guard = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['login'])->setConstructorArgs(['default', $provider, $session, $request, $timebox])->getMock();
    $events = new SignalRecorder;
    $guard->setDispatcher($events->dispatcher());
    $timebox->expects('call')->andReturnUsing(function ($callback, $microseconds) use ($timebox) {
        return $callback($timebox->expects('returnEarly')->getMock());
    });
    $user = $this->createStub(Authenticatable::class);
    $guard->getProvider()->expects('retrieveByCredentials')->andReturn($user);
    $guard->getProvider()->expects('validateCredentials')->with($user, ['foo'])->andReturn(true);
    $guard->getProvider()->expects('rehashPasswordIfRequired')->with($user, ['foo']);
    $guard->expects($this->once())->method('login')->with($user);
    expect($guard->attempt(['foo']))->toBeTrue();

    $events->assertDispatchedOnce(Attempting::class);
    $events->assertDispatchedOnce(Validated::class);
});

test('attempt returns false if user not given', function () {
    $mock = getGuard();
    $events = new SignalRecorder;
    $mock->setDispatcher($events->dispatcher());
    $timebox = $mock->getTimebox();
    $timebox->expects('call')->andReturnUsing(function ($callback, $microseconds) use ($timebox) {
        return $callback($timebox);
    });
    $mock->getProvider()->expects('retrieveByCredentials')->andReturn(null);
    $mock->getProvider()->shouldNotReceive('rehashPasswordIfRequired');
    expect($mock->attempt(['foo']))->toBeFalse();

    $events->assertDispatchedOnce(Attempting::class);
    $events->assertDispatchedOnce(Failed::class);
    $events->assertNotDispatched(Validated::class);
});

test('attempt and with callbacks', function () {
    [$session, $provider, $request, $cookie, $timebox] = getMocks();
    $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName'])->setConstructorArgs(['default', $provider, $session, $request, $timebox])->getMock();
    $events = new SignalRecorder;
    $mock->setDispatcher($events->dispatcher());
    $timebox->shouldReceive('call')->andReturnUsing(function ($callback) use ($timebox) {
        return $callback($timebox->shouldReceive('returnEarly')->getMock());
    });
    $user = Mockery::mock(Authenticatable::class);
    $mock->expects($this->once())->method('getName')->willReturn('foo');
    $user->expects('getAuthIdentifier')->andReturn('bar');
    $user->expects('getAuthPassword')->andReturn(null);
    $mock->getSession()->expects('put')->with('foo', 'bar');
    $session->expects('regenerate');
    $mock->getProvider()->expects('retrieveByCredentials')->times(3)->with(['foo'])->andReturn($user);
    $mock->getProvider()->expects('validateCredentials')->times(2)->andReturnTrue();
    $mock->getProvider()->expects('validateCredentials')->andReturnFalse();
    $mock->getProvider()->expects('rehashPasswordIfRequired')->with($user, ['foo']);

    expect($mock->attemptWhen(['foo'], function ($user, $guard) {
        expect($user)->toBeInstanceOf(Authenticatable::class);
        expect($guard)->toBeInstanceOf(SessionGuard::class);

        return true;
    }))->toBeTrue();

    expect($mock->attemptWhen(['foo'], function ($user, $guard) {
        expect($user)->toBeInstanceOf(Authenticatable::class);
        expect($guard)->toBeInstanceOf(SessionGuard::class);

        return false;
    }))->toBeFalse();

    $executed = false;

    expect($mock->attemptWhen(['foo'], false, function () use (&$executed) {
        return $executed = true;
    }))->toBeFalse();

    expect($executed)->toBeFalse();

    $events->assertDispatchedTimes(Attempting::class, 3);
    $events->assertDispatchedOnce(Login::class);
    $events->assertDispatchedOnce(Authenticated::class);
    $events->assertDispatchedTimes(Validated::class, 2);
    $events->assertDispatchedTimes(Failed::class, 2);
});

test('attempt rehashes password when required', function () {
    [$session, $provider, $request, $cookie, $timebox] = getMocks();
    $guard = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['login'])->setConstructorArgs(['default', $provider, $session, $request, $timebox])->getMock();
    $events = new SignalRecorder;
    $guard->setDispatcher($events->dispatcher());
    $timebox->expects('call')->andReturnUsing(function ($callback, $microseconds) use ($timebox) {
        return $callback($timebox->expects('returnEarly')->getMock());
    });
    $user = $this->createStub(Authenticatable::class);
    $guard->getProvider()->expects('retrieveByCredentials')->andReturn($user);
    $guard->getProvider()->expects('validateCredentials')->with($user, ['foo'])->andReturn(true);
    $guard->getProvider()->expects('rehashPasswordIfRequired')->with($user, ['foo']);
    $guard->expects($this->once())->method('login')->with($user);
    expect($guard->attempt(['foo']))->toBeTrue();

    $events->assertDispatchedOnce(Attempting::class);
    $events->assertDispatchedOnce(Validated::class);
});

test('attempt doesnt rehash password when disabled', function () {
    [$session, $provider, $request, $cookie, $timebox] = getMocks();
    $guard = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['login'])
        ->setConstructorArgs(['default', $provider, $session, $request, $timebox, $rehashOnLogin = false])
        ->getMock();
    $events = new SignalRecorder;
    $guard->setDispatcher($events->dispatcher());
    $timebox->expects('call')->andReturnUsing(function ($callback, $microseconds) use ($timebox) {
        return $callback($timebox->expects('returnEarly')->getMock());
    });
    $user = $this->createStub(Authenticatable::class);
    $guard->getProvider()->expects('retrieveByCredentials')->andReturn($user);
    $guard->getProvider()->expects('validateCredentials')->with($user, ['foo'])->andReturn(true);
    $guard->getProvider()->shouldNotReceive('rehashPasswordIfRequired');
    $guard->expects($this->once())->method('login')->with($user);
    expect($guard->attempt(['foo']))->toBeTrue();

    $events->assertDispatchedOnce(Attempting::class);
    $events->assertDispatchedOnce(Validated::class);
});

test('login stores identifier in session', function () {
    [$session, $provider, $request, $cookie] = getMocks();
    $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
    $user = Mockery::mock(Authenticatable::class);
    $mock->expects($this->once())->method('getName')->willReturn('foo');
    $user->expects('getAuthIdentifier')->andReturn('bar');
    $user->expects('getAuthPassword')->andReturn(null);
    $mock->getSession()->expects('put')->with('foo', 'bar');
    $session->expects('regenerate');
    $mock->login($user);
});

test('login stores password hash in session', function () {
    [$guard, , $session] = getRealGuard();
    $sessionId = $session->getId();
    $user = new GenericUser(['id' => 'foo', 'password' => 'bar']);

    $guard->login($user);

    expect($session->get($guard->getName()))->toBe('foo');
    expect($session->get('password_hash_default'))->toBe(hash_hmac('sha256', 'bar', 'base-key-for-password-hash-mac'));
    expect($session->getId())->not->toBe($sessionId);
});

test('login with generic user without password does not store password hash', function () {
    [$guard, , $session] = getRealGuard();
    $user = new GenericUser(['id' => 'foo']);
    $warnings = [];

    set_error_handler(function ($level, $message) use (&$warnings) {
        $warnings[] = $message;

        return true;
    }, E_WARNING);

    try {
        $guard->login($user);
    } finally {
        restore_error_handler();
    }

    expect($warnings)->toBe([]);
    expect($session->get($guard->getName()))->toBe('foo');
    expect($session->has('password_hash_default'))->toBeFalse();
});

test('session guard is macroable', function () {
    $guard = getGuard();

    $guard->macro('foo', function () {
        return 'bar';
    });

    expect($guard->foo())->toBe('bar');
});

test('login fires login and authenticated events', function () {
    [$session, $provider, $request, $cookie] = getMocks();
    $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
    $events = new SignalRecorder;
    $mock->setDispatcher($events->dispatcher());
    $user = Mockery::mock(Authenticatable::class);
    $mock->expects($this->once())->method('getName')->willReturn('foo');
    $user->expects('getAuthIdentifier')->andReturn('bar');
    $user->expects('getAuthPassword')->andReturn(null);
    $mock->getSession()->expects('put')->with('foo', 'bar');
    $session->expects('regenerate');
    $mock->login($user);

    $events->assertDispatchedOnce(Login::class);
    $events->assertDispatchedOnce(Authenticated::class);
});

test('failed attempt fires failed event', function () {
    $guard = getGuard();
    $events = new SignalRecorder;
    $guard->setDispatcher($events->dispatcher());
    $timebox = $guard->getTimebox();
    $timebox->expects('call')->andReturnUsing(function ($callback, $microseconds) use ($timebox) {
        return $callback($timebox);
    });
    $guard->getProvider()->expects('retrieveByCredentials')->with(['foo'])->andReturn(null);
    $guard->getProvider()->shouldNotReceive('rehashPasswordIfRequired');
    $guard->attempt(['foo']);

    $events->assertDispatchedOnce(Attempting::class);
    $events->assertDispatchedOnce(Failed::class);
    $events->assertNotDispatched(Validated::class);
});

test('authenticate returns user when user is not null', function () {
    $user = new GenericUser([]);
    $guard = getGuard();
    $guard->setUser($user);

    expect($guard->authenticate())->toEqual($user);
});

test('set user fires authenticated event', function () {
    $user = new GenericUser([]);
    $guard = getGuard();
    $events = new SignalRecorder;
    $guard->setDispatcher($events->dispatcher());
    $guard->setUser($user);

    $events->assertDispatchedOnce(Authenticated::class);
});

test('authenticate throws when user is null', function () {

    [$guard] = getRealGuard();

    $guard->authenticate();
})->throws(AuthenticationException::class, 'Unauthenticated.');

test('has user returns true when user is not null', function () {
    $user = new GenericUser([]);
    $guard = getGuard();
    $guard->setUser($user);

    expect($guard->hasUser())->toBeTrue();
});

test('has user returns false when user is null', function () {
    $guard = getGuard();
    $guard->getSession()->shouldNotReceive('get');

    expect($guard->hasUser())->toBeFalse();
});

test('is authed returns true when user is not null', function () {
    $user = new GenericUser([]);
    $mock = getGuard();
    $mock->setUser($user);
    expect($mock->check())->toBeTrue();
    expect($mock->guest())->toBeFalse();
});

test('is authed returns false when user is null', function () {
    [$guard] = getRealGuard();

    expect($guard->check())->toBeFalse();
    expect($guard->guest())->toBeTrue();
});

test('user method returns cached user', function () {
    $user = new GenericUser([]);
    $mock = getGuard();
    $mock->setUser($user);
    expect($mock->user())->toBe($user);
});

test('null is returned for user if no user found', function () {
    [$guard] = getRealGuard();

    expect($guard->user())->toBeNull();
});

test('user is set to retrieved user', function () {
    [$guard, $provider, $session] = getRealGuard();
    $session->put($guard->getName(), 1);
    $user = new GenericUser([]);
    $provider->expects('retrieveById')->with(1)->andReturn($user);

    expect($guard->user())->toBe($user);
    expect($guard->getUser())->toBe($user);
});

test('logout removes session token and remember me cookie', function () {
    [$session, $provider, $request, $cookie] = getMocks();
    $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName', 'getRecallerName', 'recaller'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
    $cookies = Mockery::mock(CookieJar::class);
    $mock->setCookieJar($cookies);
    $user = Mockery::mock(Authenticatable::class);
    $user->expects('getRememberToken')->andReturn('a');
    $user->expects('setRememberToken');
    $mock->expects($this->once())->method('getName')->willReturn('foo');
    $mock->expects($this->exactly(2))->method('getRecallerName')->willReturn($recallerName = 'bar');
    $mock->expects($this->once())->method('recaller')->willReturn('non-null-cookie');
    $provider->expects('updateRememberToken');

    $cookie = new Cookie('bar');
    $cookies->expects('forget')->with('bar')->andReturn($cookie);
    $cookies->expects('queue')->with($cookie);
    $cookies->expects('unqueue')->with($recallerName);
    $mock->getSession()->expects('remove')->with('foo');
    $mock->setUser($user);
    $mock->logout();
    expect($mock->getUser())->toBeNull();
});

test('logout does not enqueue remember me cookie for deletion if cookie doesnt exist', function () {
    [$session, $provider, $request, $cookie] = getMocks();
    $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName', 'getRecallerName', 'recaller'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
    $cookies = Mockery::mock(CookieJar::class);
    $mock->setCookieJar($cookies);
    $user = Mockery::mock(Authenticatable::class);
    $user->expects('getRememberToken')->andReturn(null);
    $mock->expects($this->once())->method('getRecallerName')->willReturn($recallerName = 'bar');
    $mock->expects($this->once())->method('getName')->willReturn('foo');
    $mock->expects($this->once())->method('recaller')->willReturn(null);

    $cookies->expects('unqueue')->with($recallerName);

    $mock->getSession()->expects('remove')->with('foo');
    $mock->setUser($user);
    $mock->logout();
    expect($mock->getUser())->toBeNull();
});

test('logout fires logout event', function () {
    [$guard, , $session] = getRealGuard();
    $guard->setCookieJar(getCookieJar());
    $events = new SignalRecorder;
    $guard->setDispatcher($events->dispatcher());
    $user = new GenericUser(['id' => 10, 'remember_token' => null]);
    $guard->setUser($user);
    $session->put($guard->getName(), $user->getAuthIdentifier());
    $guard->logout();

    expect($session->get($guard->getName()))->toBeNull();
    $events->assertDispatchedOnce(Authenticated::class);
    $events->assertDispatchedOnce(Logout::class);
});

test('logout does not set remember token if not previously set', function () {
    [$session, $provider, $request] = getMocks();
    $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['clearUserDataFromStorage'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
    $user = Mockery::mock(Authenticatable::class);

    $user->expects('getRememberToken')->andReturn(null);
    $user->shouldNotReceive('setRememberToken');
    $provider->shouldNotReceive('updateRememberToken');

    $mock->setUser($user);
    $mock->logout();
});

test('logout current device removes remember me cookie', function () {
    [$session, $provider, $request, $cookie] = getMocks();
    $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName', 'getRecallerName', 'recaller'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
    $cookies = Mockery::mock(CookieJar::class);
    $mock->setCookieJar($cookies);
    $user = new GenericUser([]);
    $mock->expects($this->once())->method('getName')->willReturn('foo');
    $mock->expects($this->exactly(2))->method('getRecallerName')->willReturn($recallerName = 'bar');
    $mock->expects($this->once())->method('recaller')->willReturn('non-null-cookie');

    $cookie = new Cookie('bar');
    $cookies->expects('forget')->with('bar')->andReturn($cookie);
    $cookies->expects('queue')->with($cookie);
    $cookies->expects('unqueue')->with($recallerName);
    $mock->getSession()->expects('remove')->with('foo');
    $mock->setUser($user);
    $mock->logoutCurrentDevice();
    expect($mock->getUser())->toBeNull();
});

test('logout current device does not enqueue remember me cookie for deletion if cookie doesnt exist', function () {
    [$session, $provider, $request, $cookie] = getMocks();
    $mock = $this->getMockBuilder(SessionGuard::class)->onlyMethods(['getName', 'getRecallerName', 'recaller'])->setConstructorArgs(['default', $provider, $session, $request])->getMock();
    $cookies = Mockery::mock(CookieJar::class);
    $mock->setCookieJar($cookies);
    $user = new GenericUser([]);
    $mock->expects($this->once())->method('getName')->willReturn('foo');
    $mock->expects($this->once())->method('getRecallerName')->willReturn($recallerName = 'bar');
    $mock->expects($this->once())->method('recaller')->willReturn(null);
    $cookies->expects('unqueue')->with($recallerName);

    $mock->getSession()->expects('remove')->with('foo');
    $mock->setUser($user);
    $mock->logoutCurrentDevice();
    expect($mock->getUser())->toBeNull();
});

test('logout current device fires logout event', function () {
    [$guard, , $session] = getRealGuard();
    $guard->setCookieJar(getCookieJar());
    $events = new SignalRecorder;
    $guard->setDispatcher($events->dispatcher());
    $user = new GenericUser(['id' => 10]);
    $guard->setUser($user);
    $session->put($guard->getName(), $user->getAuthIdentifier());
    $guard->logoutCurrentDevice();

    expect($session->get($guard->getName()))->toBeNull();
    $events->assertDispatchedOnce(Authenticated::class);
    $events->assertDispatchedOnce(CurrentDeviceLogout::class);
});

test('login method queues cookie when remembering', function () {
    [$session, $provider, $request, $cookie] = getMocks();
    $guard = new SessionGuard('default', $provider, $session, $request);
    $guard->setCookieJar($cookie);
    $foreverCookie = new Cookie($guard->getRecallerName(), 'foo');
    $expectedHash = hash_hmac('sha256', 'bar', 'base-key-for-password-hash-mac');
    $cookie->expects('make')->with($guard->getRecallerName(), 'foo|recaller|'.$expectedHash, 576000)->andReturn($foreverCookie);
    $cookie->expects('queue')->with($foreverCookie);
    $guard->getSession()->expects('put')->with($guard->getName(), 'foo');
    $guard->getSession()->expects('put')->with('password_hash_default', $expectedHash);
    $session->expects('regenerate');
    $user = Mockery::mock(Authenticatable::class);
    $user->expects('getAuthIdentifier')->times(2)->andReturn('foo');
    $user->expects('getAuthPassword')->times(2)->andReturn('bar');
    $user->expects('getRememberToken')->times(2)->andReturn('recaller');
    $user->shouldReceive('setRememberToken')->never();
    $provider->shouldReceive('updateRememberToken')->never();
    $guard->login($user, true);
});

test('login method queues cookie when remembering and allows override', function () {
    [$session, $provider, $request, $cookie] = getMocks();
    $guard = new SessionGuard('default', $provider, $session, $request);
    $guard->setRememberDuration(5000);
    $guard->setCookieJar($cookie);
    $foreverCookie = new Cookie($guard->getRecallerName(), 'foo');
    $expectedHash = hash_hmac('sha256', 'bar', 'base-key-for-password-hash-mac');
    $cookie->expects('make')->with($guard->getRecallerName(), 'foo|recaller|'.$expectedHash, 5000)->andReturn($foreverCookie);
    $cookie->expects('queue')->with($foreverCookie);
    $guard->getSession()->expects('put')->with($guard->getName(), 'foo');
    $guard->getSession()->expects('put')->with('password_hash_default', $expectedHash);
    $session->expects('regenerate');
    $user = Mockery::mock(Authenticatable::class);
    $user->expects('getAuthIdentifier')->times(2)->andReturn('foo');
    $user->expects('getAuthPassword')->times(2)->andReturn('bar');
    $user->expects('getRememberToken')->times(2)->andReturn('recaller');
    $user->shouldReceive('setRememberToken')->never();
    $provider->shouldReceive('updateRememberToken')->never();
    $guard->login($user, true);
});

test('login method creates remember token if one doesnt exist', function () {
    [$session, $provider, $request, $cookie] = getMocks();
    $guard = new SessionGuard('default', $provider, $session, $request);
    $guard->setCookieJar($cookie);
    $foreverCookie = new Cookie($guard->getRecallerName(), 'foo');
    $cookie->expects('make')->andReturn($foreverCookie);
    $cookie->expects('queue')->with($foreverCookie);
    $guard->getSession()->expects('put')->with($guard->getName(), 'foo');
    $guard->getSession()->expects('put')->with(
        'password_hash_default',
        hash_hmac('sha256', 'foo', 'base-key-for-password-hash-mac')
    );
    $session->expects('regenerate');
    $user = Mockery::mock(Authenticatable::class);
    $user->expects('getAuthIdentifier')->times(2)->andReturn('foo');
    $user->expects('getAuthPassword')->times(2)->andReturn('foo');
    $user->expects('getRememberToken')->times(2)->andReturn(null);
    $user->expects('setRememberToken');
    $provider->expects('updateRememberToken');
    $guard->login($user, true);
});

test('login using id logs in with user', function () {
    [$guard, $provider, $session] = getRealGuard();

    $user = new GenericUser(['id' => 10, 'password' => 'secret']);
    $provider->expects('retrieveById')->with(10)->andReturn($user);

    expect($guard->loginUsingId(10))->toBe($user);
    expect($guard->user())->toBe($user);
    expect($session->get($guard->getName()))->toBe(10);
});

test('login using id failure', function () {
    [$guard, $provider, $session] = getRealGuard();

    $provider->expects('retrieveById')->with(11)->andReturn(null);

    expect($guard->loginUsingId(11))->toBeFalse();
    expect($guard->user())->toBeNull();
    expect($session->get($guard->getName()))->toBeNull();
});

test('once using id sets user', function () {
    [$guard, $provider, $session] = getRealGuard();

    $user = new GenericUser(['id' => 10, 'password' => 'secret']);
    $provider->expects('retrieveById')->with(10)->andReturn($user);

    expect($guard->onceUsingId(10))->toBe($user);
    expect($guard->user())->toBe($user);
    expect($session->get($guard->getName()))->toBeNull();
});

test('once using id failure', function () {
    [$guard, $provider] = getRealGuard();

    $provider->expects('retrieveById')->with(11)->andReturn(null);

    expect($guard->onceUsingId(11))->toBeFalse();
    expect($guard->user())->toBeNull();
});

test('user uses remember cookie if it exists', function () {
    [$guard, $provider, $session] = getRealGuard();
    $sessionId = $session->getId();
    $guard->setRequest(Request::create('/', 'GET', [], [$guard->getRecallerName() => 'id|recaller|baz']));
    $user = Mockery::mock(Authenticatable::class);
    $provider->expects('retrieveByToken')->with('id', 'recaller')->andReturn($user);
    $user->expects('getAuthIdentifier')->andReturn('bar');
    $user->expects('getAuthPassword')->andReturn('baz');

    expect($guard->user())->toBe($user);
    expect($guard->viaRemember())->toBeTrue();
    expect($session->get($guard->getName()))->toBe('bar');
    expect($session->getId())->not->toBe($sessionId);
});

test('user returns null when remember cookie token does not match any user', function () {
    $guard = getGuard();
    [$session, $provider, $request, $cookie] = getMocks();
    $request = Request::create('/', 'GET', [], [$guard->getRecallerName() => 'id|recaller|baz']);
    $guard = new SessionGuard('default', $provider, $session, $request);
    $guard->getSession()->shouldReceive('get')->once()->with($guard->getName())->andReturn(null);
    $guard->getProvider()->shouldReceive('retrieveByToken')->once()->with('id', 'recaller')->andReturn(null);
    expect($guard->user())->toBeNull();
    expect($guard->viaRemember())->toBeFalse();
});

test('user returns null when recaller user has null password', function () {
    $guard = getGuard();
    [$session, $provider, $request, $cookie] = getMocks();
    $request = Request::create('/', 'GET', [], [$guard->getRecallerName() => 'id|recaller|baz']);
    $guard = new SessionGuard('default', $provider, $session, $request);
    $guard->getSession()->shouldReceive('get')->once()->with($guard->getName())->andReturn(null);
    $user = Mockery::mock(Authenticatable::class);
    $guard->getProvider()->shouldReceive('retrieveByToken')->once()->with('id', 'recaller')->andReturn($user);
    $user->shouldReceive('getAuthPassword')->once()->andReturn(null);
    expect($guard->user())->toBeNull();
});

test('hash password for cookie accepts null password', function () {
    $guard = getGuard();
    $deprecations = [];

    set_error_handler(function ($level, $message) use (&$deprecations) {
        $deprecations[] = $message;

        return true;
    }, E_DEPRECATED);

    try {
        $hash = $guard->hashPasswordForCookie(null);
    } finally {
        restore_error_handler();
    }

    expect($deprecations)->toBe([]);
    expect($hash)->toBe($guard->hashPasswordForCookie(''));
});

test('login once sets user', function () {
    [$guard, $provider, $session] = getRealGuard();

    $user = new GenericUser(['id' => 10, 'password' => 'secret']);
    $provider->expects('retrieveByCredentials')->with(['foo'])->andReturn($user);
    $provider->expects('validateCredentials')->with($user, ['foo'])->andReturn(true);
    $provider->expects('rehashPasswordIfRequired')->with($user, ['foo']);

    expect($guard->once(['foo']))->toBeTrue();
    expect($guard->user())->toBe($user);
    expect($session->get($guard->getName()))->toBeNull();
});

test('login once failure', function () {
    [$guard, $provider] = getRealGuard();

    $user = new GenericUser(['id' => 10, 'password' => 'secret']);
    $provider->expects('retrieveByCredentials')->with(['foo'])->andReturn($user);
    $provider->expects('validateCredentials')->with($user, ['foo'])->andReturn(false);
    $provider->shouldNotReceive('rehashPasswordIfRequired');

    expect($guard->once(['foo']))->toBeFalse();
    expect($guard->user())->toBeNull();
});

test('forget user sets user to null', function () {
    $user = new GenericUser([]);
    $guard = getGuard();
    $guard->setUser($user);
    $guard->forgetUser();
    expect($guard->getUser())->toBeNull();
});

function getRealGuard()
{
    $session = new Store('test', new ArraySessionHandler(10));
    $provider = Mockery::mock(UserProvider::class);

    return [new SessionGuard('default', $provider, $session, timeboxDuration: 0), $provider, $session];
}

function getGuard()
{
    [$session, $provider, $request, $cookie, $timebox] = getMocks();

    return new SessionGuard('default', $provider, $session, $request, $timebox);
}

function getMocks()
{
    return [
        Mockery::mock(Session::class),
        Mockery::mock(UserProvider::class),
        Request::create('/', 'GET'),
        Mockery::mock(CookieJar::class),
        Mockery::mock(Timebox::class),
    ];
}

function getCookieJar()
{
    return new CookieJar(Request::create('/foo', 'GET'), Mockery::mock(Encrypter::class), ['domain' => 'foo.com', 'path' => '/', 'secure' => false, 'httpOnly' => false]);
}
