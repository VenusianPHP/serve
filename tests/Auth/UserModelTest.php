<?php

use Mariner\Auth\User;
use Mariner\Contracts\Auth\Access\Authorizable;
use Mariner\Contracts\Auth\Access\Gate;
use Mariner\Contracts\Auth\Authenticatable;
use Mariner\Contracts\Auth\CanResetPassword;
use Venusian\Serve\Tests\Fixtures\WebApp;

class AppUser extends User
{
    protected $guarded = [];
}

beforeEach(function () {
    $this->app = WebApp::boot();
    $gate = $this->app->make(Gate::class);
    $gate->define('edit', fn (AppUser $user, string $post) => $post === "post-of-{$user->id}");
    $gate->define('admin', fn (AppUser $user) => $user->role === 'admin');
});
afterEach(fn () => WebApp::tearDown($this));

it('is an authenticatable, authorizable, resettable user', function () {
    $user = new AppUser(['id' => 3, 'email' => 'ada@example.test', 'password' => 'hashed']);

    expect($user)->toBeInstanceOf(Authenticatable::class)
        ->toBeInstanceOf(Authorizable::class)
        ->toBeInstanceOf(CanResetPassword::class)
        ->and($user->getAuthIdentifierName())->toBe('id')
        ->and($user->getAuthIdentifier())->toBe(3)
        ->and($user->getAuthPassword())->toBe('hashed')
        ->and($user->getEmailForPasswordReset())->toBe('ada@example.test')
        ->and(method_exists($user, 'hasVerifiedEmail'))->toBeTrue();
});

it('asks the gate what it can do', function () {
    $user = new AppUser(['id' => 3, 'role' => 'editor']);

    expect($user->can('edit', 'post-of-3'))->toBeTrue()
        ->and($user->cant('edit', 'post-of-4'))->toBeTrue()
        ->and($user->cannot('admin'))->toBeTrue()
        ->and($user->canAny(['admin', 'edit'], 'post-of-3'))->toBeTrue()
        ->and($user->canAny(['admin', 'edit'], 'post-of-4'))->toBeFalse();
});
