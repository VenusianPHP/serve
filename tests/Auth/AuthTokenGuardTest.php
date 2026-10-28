<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\TokenGuard;
use Mariner\Contracts\Auth\UserProvider;
use Mariner\Http\Request;
use Mockery;

test('user can be retrieved by query string variable', function () {
    $provider = Mockery::mock(UserProvider::class);
    $user = new AuthTokenGuardTestUser;
    $user->id = 1;
    $provider->expects('retrieveByCredentials')->with(['api_token' => 'foo'])->andReturn($user);
    $request = Request::create('/', 'GET', ['api_token' => 'foo']);

    $guard = new TokenGuard($provider, $request);

    $user = $guard->user();

    expect($user->id)->toBe(1);
    expect($guard->check())->toBeTrue();
    expect($guard->guest())->toBeFalse();
    expect($guard->id())->toBe(1);
});

test('token can be hashed', function () {
    $provider = Mockery::mock(UserProvider::class);
    $user = new AuthTokenGuardTestUser;
    $user->id = 1;
    $provider->expects('retrieveByCredentials')->with(['api_token' => hash('sha256', 'foo')])->andReturn($user);
    $request = Request::create('/', 'GET', ['api_token' => 'foo']);

    $guard = new TokenGuard($provider, $request, 'api_token', 'api_token', $hash = true);

    $user = $guard->user();

    expect($user->id)->toBe(1);
    expect($guard->check())->toBeTrue();
    expect($guard->guest())->toBeFalse();
    expect($guard->id())->toBe(1);
});

test('user cannot be retrieved with non string token', function () {
    $provider = Mockery::mock(UserProvider::class);
    $provider->shouldNotReceive('retrieveByCredentials');
    $request = Request::create('/', 'GET', ['api_token' => [0]]);

    $guard = new TokenGuard($provider, $request);

    expect($guard->user())->toBeNull();
});

test('user can be retrieved by auth headers', function () {
    $provider = Mockery::mock(UserProvider::class);
    $provider->expects('retrieveByCredentials')->with(['api_token' => 'foo'])->andReturn((object) ['id' => 1]);
    $request = Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo', 'PHP_AUTH_PW' => 'foo']);

    $guard = new TokenGuard($provider, $request);

    $user = $guard->user();

    expect($user->id)->toBe(1);
});

test('user can be retrieved by bearer token', function () {
    $provider = Mockery::mock(UserProvider::class);
    $provider->expects('retrieveByCredentials')->with(['api_token' => 'foo'])->andReturn((object) ['id' => 1]);
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer foo']);

    $guard = new TokenGuard($provider, $request);

    $user = $guard->user();

    expect($user->id)->toBe(1);
});

test('validate can determine if credentials are valid', function () {
    $provider = Mockery::mock(UserProvider::class);
    $user = new AuthTokenGuardTestUser;
    $user->id = 1;
    $provider->expects('retrieveByCredentials')->with(['api_token' => 'foo'])->andReturn($user);
    $request = Request::create('/', 'GET', ['api_token' => 'foo']);

    $guard = new TokenGuard($provider, $request);

    expect($guard->validate(['api_token' => 'foo']))->toBeTrue();
});

test('validate can determine if credentials are invalid', function () {
    $provider = Mockery::mock(UserProvider::class);
    $provider->expects('retrieveByCredentials')->with(['api_token' => 'foo'])->andReturn(null);
    $request = Request::create('/', 'GET', ['api_token' => 'foo']);

    $guard = new TokenGuard($provider, $request);

    expect($guard->validate(['api_token' => 'foo']))->toBeFalse();
});

test('validate if api token is empty', function () {
    $provider = Mockery::mock(UserProvider::class);
    $request = Request::create('/', 'GET', ['api_token' => '']);

    $guard = new TokenGuard($provider, $request);

    expect($guard->validate(['api_token' => '']))->toBeFalse();
});

test('validate rejects non string token', function () {
    $provider = Mockery::mock(UserProvider::class);
    $provider->shouldNotReceive('retrieveByCredentials');
    $request = Request::create('/');

    $guard = new TokenGuard($provider, $request);

    expect($guard->validate(['api_token' => [0]]))->toBeFalse();
});

test('it allows to pass custom request in setter and use it for validation', function () {
    $provider = Mockery::mock(UserProvider::class);
    $user = new AuthTokenGuardTestUser;
    $user->id = 1;
    $provider->expects('retrieveByCredentials')->with(['api_token' => 'custom'])->andReturn($user);
    $request = Request::create('/', 'GET', ['api_token' => 'foo']);

    $guard = new TokenGuard($provider, $request);
    $guard->setRequest(Request::create('/', 'GET', ['api_token' => 'custom']));

    $user = $guard->user();

    expect($user->id)->toBe(1);
});

test('user can be retrieved by bearer token with custom key', function () {
    $provider = Mockery::mock(UserProvider::class);
    $provider->expects('retrieveByCredentials')->with(['custom_token_field' => 'foo'])->andReturn((object) ['id' => 1]);
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer foo']);

    $guard = new TokenGuard($provider, $request, 'custom_token_field', 'custom_token_field');

    $user = $guard->user();

    expect($user->id)->toBe(1);
});

test('user can be retrieved by query string variable with custom key', function () {
    $provider = Mockery::mock(UserProvider::class);
    $user = new AuthTokenGuardTestUser;
    $user->id = 1;
    $provider->expects('retrieveByCredentials')->with(['custom_token_field' => 'foo'])->andReturn($user);
    $request = Request::create('/', 'GET', ['custom_token_field' => 'foo']);

    $guard = new TokenGuard($provider, $request, 'custom_token_field', 'custom_token_field');

    $user = $guard->user();

    expect($user->id)->toBe(1);
    expect($guard->check())->toBeTrue();
    expect($guard->guest())->toBeFalse();
    expect($guard->id())->toBe(1);
});

test('user can be retrieved by auth headers with custom field', function () {
    $provider = Mockery::mock(UserProvider::class);
    $provider->expects('retrieveByCredentials')->with(['custom_token_field' => 'foo'])->andReturn((object) ['id' => 1]);
    $request = Request::create('/', 'GET', [], [], [], ['PHP_AUTH_USER' => 'foo', 'PHP_AUTH_PW' => 'foo']);

    $guard = new TokenGuard($provider, $request, 'custom_token_field', 'custom_token_field');

    $user = $guard->user();

    expect($user->id)->toBe(1);
});

test('validate can determine if credentials are valid with custom key', function () {
    $provider = Mockery::mock(UserProvider::class);
    $user = new AuthTokenGuardTestUser;
    $user->id = 1;
    $provider->expects('retrieveByCredentials')->with(['custom_token_field' => 'foo'])->andReturn($user);
    $request = Request::create('/', 'GET', ['custom_token_field' => 'foo']);

    $guard = new TokenGuard($provider, $request, 'custom_token_field', 'custom_token_field');

    expect($guard->validate(['custom_token_field' => 'foo']))->toBeTrue();
});

test('validate can determine if credentials are invalid with custom key', function () {
    $provider = Mockery::mock(UserProvider::class);
    $provider->expects('retrieveByCredentials')->with(['custom_token_field' => 'foo'])->andReturn(null);
    $request = Request::create('/', 'GET', ['custom_token_field' => 'foo']);

    $guard = new TokenGuard($provider, $request, 'custom_token_field', 'custom_token_field');

    expect($guard->validate(['custom_token_field' => 'foo']))->toBeFalse();
});

test('validate if api token is empty with custom key', function () {
    $provider = Mockery::mock(UserProvider::class);
    $request = Request::create('/', 'GET', ['custom_token_field' => '']);

    $guard = new TokenGuard($provider, $request, 'custom_token_field', 'custom_token_field');

    expect($guard->validate(['custom_token_field' => '']))->toBeFalse();
});

class AuthTokenGuardTestUser
{
    public $id;

    public function getAuthIdentifier()
    {
        return $this->id;
    }
}
