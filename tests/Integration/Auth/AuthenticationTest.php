<?php

namespace Venusian\Serve\Tests\Integration\Auth;

use Mariner\Auth\InstrumentUserProvider;
use Mariner\Auth\Events\Attempting;
use Mariner\Auth\Events\Authenticated;
use Mariner\Auth\Events\Failed;
use Mariner\Auth\Events\Login;
use Mariner\Auth\Events\Logout;
use Mariner\Auth\Events\OtherDeviceLogout;
use Mariner\Auth\Events\Validated;
use Mariner\Auth\SessionGuard;
use Voyager\Database\Schema\Blueprint;
use Voyager\Database\Testing\RefreshDatabase;
use Mariner\NutsAndBolts\Facades\Auth;
use Voyager\NutsAndBolts\Str;
use Venusian\Serve\Tests\Integration\Auth\Fixtures\AuthenticationTestUser;
use InvalidArgumentException;
use Venusian\Serve\Tests\Integration\IntegrationTestCase;
use Venusian\Serve\Tests\Fixtures\SignalRecorder;
use Voyager\Contracts\Signals\SignalDispatcher;

uses(RefreshDatabase::class);

beforeAll(fn () => IntegrationTestCase::withMigrations('laravel'));
afterAll(fn () => IntegrationTestCase::withMigrations());

beforeEach(function () {
    $app = $this->app;
    $app['config']->set([
        'auth.providers.users.model' => AuthenticationTestUser::class,
        'hashing.driver' => 'bcrypt',
    ]);
});

beforeEach(function () {
    $router = $this->app['router'];
    $router->get('basic', function () {
        return $this->app['auth']->guard()->basic()
            ?: $this->app['auth']->user()->toJson();
    });

    $router->get('basicWithCondition', function () {
        return $this->app['auth']->guard()->basic('email', ['is_active' => true])
            ?: $this->app['auth']->user()->toJson();
    });
});

beforeEach(function () {
    app('db')->connection()->getSchemaBuilder()->table('users', function (Blueprint $table) {
        $table->renameColumn('name', 'username');
    });

    app('db')->connection()->getSchemaBuilder()->table('users', function (Blueprint $table) {
        $table->tinyInteger('is_active')->default(0);
    });

    AuthenticationTestUser::create([
        'username' => 'username',
        'email' => 'email',
        'password' => app('hash')->driver('bcrypt')->make('password'),
        'is_active' => true,
    ]);
});

test('basic auth protects route', function () {
    $this->get('basic')->assertStatus(401);
});

test('basic auth passes on correct credentials', function () {
    $response = $this->get('basic', [
        'Authorization' => 'Basic '.base64_encode('email:password'),
    ]);

    $response->assertStatus(200);
    expect($response->json()['email'])->toBe('email');
});

test('basic auth fails on wrong credentials', function () {
    $this->get('basic', [
        'Authorization' => 'Basic '.base64_encode('email:wrong_password'),
    ])->assertStatus(401);
});

test('logging in fails via attempt', function () {
    $events = new SignalRecorder;
    $this->instance('signals', $events);
    $this->instance(SignalDispatcher::class, $events);

    expect($this->app['auth']->attempt(['email' => 'wrong', 'password' => 'password']))->toBeFalse();

    expect($this->app['auth']->check())->toBeFalse();
    expect($this->app['auth']->user())->toBeNull();

    $events->assertDispatched(Attempting::class, function ($event) {
        expect($event->guard)->toBe('web');
        expect($event->credentials)->toEqual(['email' => 'wrong', 'password' => 'password']);

        return true;
    });
    $events->assertNotDispatched(Validated::class);

    $events->assertDispatched(Failed::class, function ($event) {
        expect($event->guard)->toBe('web');
        expect($event->credentials)->toEqual(['email' => 'wrong', 'password' => 'password']);
        expect($event->user)->toBeNull();

        return true;
    });
});

test('logging in succeeds via attempt', function () {
    $events = new SignalRecorder;
    $this->instance('signals', $events);
    $this->instance(SignalDispatcher::class, $events);

    expect($this->app['auth']->attempt(['email' => 'email', 'password' => 'password']))->toBeTrue();
    expect($this->app['auth']->user())->toBeInstanceOf(AuthenticationTestUser::class);
    expect($this->app['auth']->check())->toBeTrue();
    expect($this->app['session']->get('password_hash_web'))->toBe($this->app['auth']->guard()->hashPasswordForCookie($this->app['auth']->user()->getAuthPassword()));

    $events->assertDispatched(Attempting::class, function ($event) {
        expect($event->guard)->toBe('web');
        expect($event->credentials)->toEqual(['email' => 'email', 'password' => 'password']);

        return true;
    });
    $events->assertDispatched(Validated::class, function ($event) {
        expect($event->guard)->toBe('web');
        expect($event->user->id)->toEqual(1);

        return true;
    });
    $events->assertDispatched(Login::class, function ($event) {
        expect($event->guard)->toBe('web');
        expect($event->user->id)->toEqual(1);

        return true;
    });
    $events->assertDispatched(Authenticated::class, function ($event) {
        expect($event->guard)->toBe('web');
        expect($event->user->id)->toEqual(1);

        return true;
    });
});

test('logging in using id', function () {
    $this->app['auth']->loginUsingId(1);
    expect($this->app['auth']->user()->id)->toEqual(1);

    expect($this->app['auth']->loginUsingId(1000))->toBeFalse();
});

test('logging out', function () {
    $events = new SignalRecorder;
    $this->instance('signals', $events);
    $this->instance(SignalDispatcher::class, $events);

    $this->app['auth']->loginUsingId(1);
    expect($this->app['auth']->user()->id)->toEqual(1);

    $this->app['auth']->logout();
    expect($this->app['auth']->user())->toBeNull();
    $events->assertDispatched(Logout::class, function ($event) {
        expect($event->guard)->toBe('web');
        expect($event->user->id)->toEqual(1);

        return true;
    });
});

test('logging out other devices', function () {
    $events = new SignalRecorder;
    $this->instance('signals', $events);
    $this->instance(SignalDispatcher::class, $events);

    $this->app['auth']->loginUsingId(1);

    $user = $this->app['auth']->user();

    expect($user->id)->toEqual(1);

    $this->app['auth']->logoutOtherDevices('password');
    expect($user->id)->toEqual(1);

    $events->assertDispatched(OtherDeviceLogout::class, function ($event) {
        expect($event->guard)->toBe('web');
        expect($event->user->id)->toEqual(1);

        return true;
    });
});

test('password must be valid to log out other devices', function () {

    $this->app['auth']->loginUsingId(1);

    $user = $this->app['auth']->user();

    expect($user->id)->toEqual(1);

    $this->app['auth']->logoutOtherDevices('adifferentpassword');
})->throws(InvalidArgumentException::class, 'current password');

test('logging in out via attempt remembering', function () {
    expect($this->app['auth']->attempt(['email' => 'email', 'password' => 'password'], true))->toBeTrue();
    expect($this->app['auth']->user())->toBeInstanceOf(AuthenticationTestUser::class);
    expect($this->app['auth']->check())->toBeTrue();
    expect($this->app['auth']->user()->getRememberToken())->not->toBeNull();

    $oldToken = $this->app['auth']->user()->getRememberToken();
    $user = $this->app['auth']->user();

    $this->app['auth']->logout();

    expect($user->getRememberToken())->not->toBeNull();
    expect($user->getRememberToken())->not->toEqual($oldToken);
});

test('logging in out current device via remembering', function () {
    expect($this->app['auth']->attempt(['email' => 'email', 'password' => 'password'], true))->toBeTrue();
    expect($this->app['auth']->user())->toBeInstanceOf(AuthenticationTestUser::class);
    expect($this->app['auth']->check())->toBeTrue();
    expect($this->app['auth']->user()->getRememberToken())->not->toBeNull();

    $oldToken = $this->app['auth']->user()->getRememberToken();
    $user = $this->app['auth']->user();

    $this->app['auth']->logoutCurrentDevice();

    expect($user->getRememberToken())->not->toBeNull();
    expect($user->getRememberToken())->toEqual($oldToken);
});

test('auth via attempt remembering', function () {
    $provider = new InstrumentUserProvider(app('hash'), AuthenticationTestUser::class);

    $user = AuthenticationTestUser::create([
        'username' => 'username2',
        'email' => 'email2',
        'password' => app('hash')->driver('bcrypt')->make('password'),
        'remember_token' => $token = Str::random(),
        'is_active' => false,
    ]);

    expect($provider->retrieveByToken($user->id, $token)->id)->toEqual($user->id);

    $user->update([
        'remember_token' => null,
    ]);

    expect($provider->retrieveByToken($user->id, $token))->toBeNull();
});

test('dispatcher changes if there is one on the auth guard', function () {
    expect($this->app['auth']->guard())->toBeInstanceOf(SessionGuard::class);
    expect($this->app['auth']->guard()->getDispatcher())->toBeInstanceOf(SignalDispatcher::class);

    $events = new SignalRecorder;

    $this->instance('signals', $events);
    $this->instance(SignalDispatcher::class, $events);

    expect($this->app['auth']->guard())->toBeInstanceOf(SessionGuard::class);
    expect($this->app['auth']->guard()->getDispatcher())->toBeInstanceOf(SignalRecorder::class);
});

test('dispatcher changes if there is one on the custom auth guard', function () {
    $this->app['config']['auth.guards.myGuard'] = [
        'driver' => 'myCustomDriver',
        'provider' => 'user',
    ];

    Auth::extend('myCustomDriver', function () {
        return new MyCustomGuardStub;
    });

    expect($this->app['auth']->guard('myGuard'))->toBeInstanceOf(MyCustomGuardStub::class);
    expect($this->app['auth']->guard()->getDispatcher())->toBeInstanceOf(SignalDispatcher::class);

    $events = new SignalRecorder;

    $this->instance('signals', $events);
    $this->instance(SignalDispatcher::class, $events);

    expect($this->app['auth']->guard('myGuard'))->toBeInstanceOf(MyCustomGuardStub::class);
    expect($this->app['auth']->guard()->getDispatcher())->toBeInstanceOf(SignalRecorder::class);
});

test('basic auth respects additional conditions', function () {
    AuthenticationTestUser::create([
        'username' => 'username2',
        'email' => 'email2',
        'password' => app('hash')->driver('bcrypt')->make('password'),
        'is_active' => false,
    ]);

    $this->get('basicWithCondition', [
        'Authorization' => 'Basic '.base64_encode('email2:password2'),
    ])->assertStatus(401);

    $this->get('basicWithCondition', [
        'Authorization' => 'Basic '.base64_encode('email:password'),
    ])->assertStatus(200);
});

test('has no problem if there is no dispatching the auth custom guard', function () {
    $this->app['config']['auth.guards.myGuard'] = [
        'driver' => 'myCustomDriver',
        'provider' => 'user',
    ];

    Auth::extend('myCustomDriver', function () {
        return new MyDispatcherLessCustomGuardStub;
    });

    expect($this->app['auth']->guard('myGuard'))->toBeInstanceOf(MyDispatcherLessCustomGuardStub::class);

    $events = new SignalRecorder;

    $this->instance('signals', $events);
    $this->instance(SignalDispatcher::class, $events);

    expect($this->app['auth']->guard('myGuard'))->toBeInstanceOf(MyDispatcherLessCustomGuardStub::class);
});

class MyCustomGuardStub
{
    protected $events;

    public function __construct()
    {
        $this->setDispatcher(new \Voyager\Signals\SignalDispatcher);
    }

    public function setDispatcher(SignalDispatcher $events)
    {
        $this->events = $events;
    }

    public function getDispatcher()
    {
        return $this->events;
    }
}

class MyDispatcherLessCustomGuardStub
{
    //
}
