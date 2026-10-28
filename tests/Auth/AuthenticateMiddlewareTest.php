<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\AuthenticationException;
use Mariner\Auth\AuthManager;
use Mariner\Auth\InstrumentUserProvider;
use Mariner\Auth\Middleware\Authenticate;
use Mariner\Auth\Middleware\AuthenticateWithBasicAuth;
use Mariner\Auth\RequestGuard;
use Voyager\Config\Repository;
use Voyager\Config\Repository as Config;
use Voyager\Vessel\ControlPanel;
use Mariner\Http\Request;
use Mockery;
use stdClass;

beforeEach(function () {
    $container = ControlPanel::setInstance(new ControlPanel);

    $this->auth = new AuthManager($container);

    $container->registerSingleton('config', function () {
        return createConfig();
    });
});

afterEach(function () {
    ControlPanel::setInstance(null);
});

test('it can generate definition via static method', function () {
    $signature = Authenticate::using('foo');
    expect($signature)->toBe('Mariner\Auth\Middleware\Authenticate:foo');

    $signature = Authenticate::using('foo', 'bar');
    expect($signature)->toBe('Mariner\Auth\Middleware\Authenticate:foo,bar');

    $signature = Authenticate::using('foo', 'bar', 'baz');
    expect($signature)->toBe('Mariner\Auth\Middleware\Authenticate:foo,bar,baz');
});

test('it can generate definition via static method for basic', function () {
    $signature = AuthenticateWithBasicAuth::using('guard');
    expect($signature)->toBe('Mariner\Auth\Middleware\AuthenticateWithBasicAuth:guard');

    $signature = AuthenticateWithBasicAuth::using('guard', 'field');
    expect($signature)->toBe('Mariner\Auth\Middleware\AuthenticateWithBasicAuth:guard,field');

    $signature = AuthenticateWithBasicAuth::using(field: 'field');
    expect($signature)->toBe('Mariner\Auth\Middleware\AuthenticateWithBasicAuth:,field');
});

test('default unauthenticated throws', function () {
    registerAuthDriver($this->auth, 'default', false);

    authenticate($this->auth);
})->throws(AuthenticationException::class, 'Unauthenticated.');

test('default unauthenticated throws with guards', function () {
    try {
        registerAuthDriver($this->auth, 'default', false);

        authenticate($this->auth, 'default');
    } catch (AuthenticationException $e) {
        expect($e->guards())->toContain('default');

        return;
    }

    $this->fail();
});

test('default authenticated keeps default driver', function () {
    $driver = registerAuthDriver($this->auth, 'default', true);

    authenticate($this->auth);

    expect($this->auth->guard())->toBe($driver);
});

test('secondary authenticated updates default driver', function () {
    registerAuthDriver($this->auth, 'default', false);

    $secondary = registerAuthDriver($this->auth, 'secondary', true);

    authenticate($this->auth, 'secondary');

    expect($this->auth->guard())->toBe($secondary);
});

test('multiple drivers unauthenticated throws', function () {

    registerAuthDriver($this->auth, 'default', false);

    registerAuthDriver($this->auth, 'secondary', false);

    authenticate($this->auth, 'default', 'secondary');
})->throws(AuthenticationException::class, 'Unauthenticated.');

test('multiple drivers unauthenticated throws with guards', function () {
    $expectedGuards = ['default', 'secondary'];

    try {
        registerAuthDriver($this->auth, 'default', false);

        registerAuthDriver($this->auth, 'secondary', false);

        authenticate($this->auth, ...$expectedGuards);
    } catch (AuthenticationException $e) {
        expect($e->guards())->toEqual($expectedGuards);

        return;
    }

    $this->fail();
});

test('multiple drivers authenticated updates default', function () {
    registerAuthDriver($this->auth, 'default', false);

    $secondary = registerAuthDriver($this->auth, 'secondary', true);

    authenticate($this->auth, 'default', 'secondary');

    expect($this->auth->guard())->toBe($secondary);
});

test('custom driver closure bound object is auth manager', function () {
    $this->auth->extend(CustomAuthDriver::class, fn () => $this);
    expect($this->auth->guard(CustomAuthDriver::class))->toBe($this->auth);
});

test('custom driver static', function () {
    $driver = new stdClass;

    $this->auth->extend(CustomAuthDriver::class, fn () => $driver);
    expect($this->auth->guard(CustomAuthDriver::class))->toBe($driver);
});

test('custom invokable driver', function () {
    $driver = new stdClass;
    $creator = new CustomAuthDriver($driver);

    $this->auth->extend(CustomAuthDriver::class, $creator(...));
    expect($this->auth->guard(CustomAuthDriver::class))->toBe($driver);
});

test('auth manager can resolve backed enum guard', function () {
    $driver = registerAuthDriver($this->auth, 'default', true);

    $guard1 = $this->auth->guard(GuardName::Default);
    $guard2 = $this->auth->guard('default');

    expect($guard2)->toBe($guard1);
    expect($guard1)->toBe($driver);
});

test('should use accepts backed enum', function () {
    registerAuthDriver($this->auth, 'default', true);
    $secondary = registerAuthDriver($this->auth, 'secondary', true);

    $this->auth->shouldUse(GuardName::Secondary);

    expect($this->auth->getDefaultDriver())->toBe('secondary');
    expect($this->auth->guard())->toBe($secondary);
});

test('set default driver accepts backed enum', function () {
    $this->auth->setDefaultDriver(GuardName::Secondary);

    expect($this->auth->getDefaultDriver())->toBe('secondary');
});

function createConfig()
{
    return new Config([
        'auth' => [
            'defaults' => ['guard' => 'default'],
            'guards' => [
                'default' => ['driver' => 'default'],
                'secondary' => ['driver' => 'secondary'],
                CustomAuthDriver::class => ['driver' => CustomAuthDriver::class],
            ],
        ],
    ]);
}

function registerAuthDriver(AuthManager $auth, $name, $authenticated)
{
    $driver = createAuthDriver($authenticated);

    $auth->extend($name, function () use ($driver) {
        return $driver;
    });

    return $driver;
}

function createAuthDriver($authenticated)
{
    return new RequestGuard(function () use ($authenticated) {
        return $authenticated ? new stdClass : null;
    }, new Request, Mockery::mock(InstrumentUserProvider::class));
}

function authenticate(AuthManager $auth, ...$guards)
{
    $request = Request::create('/');

    $nextParam = null;

    $next = function ($param) use (&$nextParam) {
        $nextParam = $param;
    };

    (new Authenticate($auth))->handle($request, $next, ...$guards);

    expect($nextParam)->toBe($request);
}

enum GuardName: string
{
    case Default = 'default';
    case Secondary = 'secondary';
}

class CustomAuthDriver
{
    public function __construct(private object $driver)
    {
    }

    public function __invoke()
    {
        return $this->driver;
    }
}
