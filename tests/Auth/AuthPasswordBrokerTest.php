<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\Passwords\PasswordBroker;
use Mariner\Auth\Passwords\TokenRepositoryInterface;
use Mariner\Contracts\Auth\CanResetPassword;
use Mariner\Contracts\Auth\PasswordBroker as PasswordBrokerContract;
use Mariner\Auth\User;
use Mariner\Contracts\Auth\UserProvider;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Mockery;
use UnexpectedValueException;

test('if user is not found error redirect is returned', function () {
    $broker = passwordBroker($mocks = passwordBrokerMocks());
    $mocks['users']->expects('retrieveByCredentials')->with(['credentials'])->andReturnNull();

    expect($broker->sendResetLink(['credentials']))->toBe(PasswordBrokerContract::INVALID_USER);
});

test('get user throws exception if user doesnt implement can reset password', function () {

    $broker = passwordBroker($mocks = passwordBrokerMocks());
    $mocks['users']->expects('retrieveByCredentials')->with(['foo'])->andReturn('bar');

    $broker->getUser(['foo']);
})->throws(UnexpectedValueException::class, 'User must implement CanResetPassword interface.');

test('broker creates token and redirects without error', function () {
    $broker = passwordBroker($mocks = passwordBrokerMocks());
    $user = Mockery::mock(CanResetPassword::class);
    $mocks['users']->expects('retrieveByCredentials')->with(['foo'])->andReturn($user);
    $mocks['tokens']->expects('recentlyCreatedToken')->with($user)->andReturn(false);
    $mocks['tokens']->expects('create')->with($user)->andReturn('token');
    $user->expects('sendPasswordResetNotification')->with('token');

    expect($broker->sendResetLink(['foo']))->toBe(PasswordBrokerContract::RESET_LINK_SENT);
});

test('redirect is returned by reset when user credentials invalid', function () {
    $broker = passwordBroker($mocks = passwordBrokerMocks());
    $mocks['users']->expects('retrieveByCredentials')->with(['creds'])->andReturn(null);

    expect($broker->reset(['creds'], function () {
        //
    }))->toBe(PasswordBrokerContract::INVALID_USER);
});

test('if token is recently created', function () {
    $broker = passwordBroker($mocks = passwordBrokerMocks());
    $user = new User;
    $mocks['users']->expects('retrieveByCredentials')->with(['foo'])->andReturn($user);
    $mocks['tokens']->expects('recentlyCreatedToken')->with($user)->andReturn(true);

    expect($broker->sendResetLink(['foo']))->toBe(PasswordBrokerContract::RESET_THROTTLED);
});

test('user is retrieved by credentials', function () {
    $broker = passwordBroker($mocks = passwordBrokerMocks());
    $user = new User;
    $mocks['users']->expects('retrieveByCredentials')->with(['foo'])->andReturn($user);

    expect($broker->getUser(['foo']))->toEqual($user);
});

test('redirect returned by remind when record doesnt exist in table', function () {
    $creds = ['token' => 'token'];
    $broker = passwordBroker($mocks = passwordBrokerMocks());
    $user = new User;
    $mocks['users']->expects('retrieveByCredentials')->with(Arr::except($creds, ['token']))->andReturn($user);
    $mocks['tokens']->expects('exists')->with($user, 'token')->andReturn(false);

    expect($broker->reset($creds, function () {
        //
    }))->toBe(PasswordBrokerContract::INVALID_TOKEN);
});

test('reset removes record on reminder table and calls callback', function () {
    unset($_SERVER['__password.reset.test']);
    $broker = passwordBroker($mocks = passwordBrokerMocks());
    $user = new User;
    $mocks['users']->expects('retrieveByCredentials')->with(['password' => 'password'])->andReturn($user);
    $mocks['tokens']->expects('exists')->with($user, 'token')->andReturn(true);
    $mocks['tokens']->expects('delete')->with($user);
    $callback = function ($user, $password) {
        $_SERVER['__password.reset.test'] = ['user' => $user, 'password' => $password];

        return 'foo';
    };

    expect($broker->reset(['password' => 'password', 'token' => 'token'], $callback))->toBe(PasswordBrokerContract::PASSWORD_RESET);
    expect($_SERVER['__password.reset.test'])->toEqual(['user' => $user, 'password' => 'password']);
});

test('executes callback instead of sending notification', function () {
    $executed = false;

    $closure = function () use (&$executed) {
        $executed = true;
    };

    $broker = passwordBroker($mocks = passwordBrokerMocks());
    $user = new User;
    $mocks['users']->expects('retrieveByCredentials')->with(['foo'])->andReturn($user);
    $mocks['tokens']->expects('recentlyCreatedToken')->with($user)->andReturn(false);
    $mocks['tokens']->expects('create')->with($user)->andReturn('token');

    expect($broker->sendResetLink(['foo'], $closure))->toEqual(PasswordBrokerContract::RESET_LINK_SENT);

    expect($executed)->toBeTrue();
});

function passwordBroker($mocks)
{
    return new PasswordBroker($mocks['tokens'], $mocks['users']);
}

function passwordBrokerMocks()
{
    return [
        'tokens' => Mockery::mock(TokenRepositoryInterface::class),
        'users' => Mockery::mock(UserProvider::class),
    ];
}
