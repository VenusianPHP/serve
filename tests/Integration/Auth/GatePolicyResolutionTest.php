<?php

namespace Venusian\Serve\Tests\Integration\Auth;

use Mariner\Auth\Access\Events\GateEvaluated;
use Voyager\Database\Instrument\Attributes\UsePolicy;
use Voyager\Database\Instrument\Model;
use Voyager\Contracts\Signals\SignalDispatcher;
use Venusian\Serve\Tests\Fixtures\SignalRecorder;
use Mariner\NutsAndBolts\Facades\Gate;
use Venusian\Serve\Tests\Integration\Auth\Fixtures\AuthenticationTestUser;
use Venusian\Serve\Tests\Integration\Auth\Fixtures\Models\Policies\Nested\SubTestUserPolicy;
use Venusian\Serve\Tests\Integration\Auth\Fixtures\Policies\AuthenticationTestUserPolicy;
use Venusian\Serve\Tests\Integration\Auth\Fixtures\Policies\Nested\TopTestUserPolicy;

test('gate evaluation event is fired', function () {
    $events = new SignalRecorder;
    $this->instance('signals', $events);
    $this->instance(SignalDispatcher::class, $events);

    Gate::check('foo');

    $events->assertDispatched(GateEvaluated::class);
});

test('policy can be guessed using class conventions', function () {
    expect(Gate::getPolicyFor(AuthenticationTestUser::class))->toBeInstanceOf(AuthenticationTestUserPolicy::class);

    expect(Gate::getPolicyFor(Fixtures\Models\AuthenticationTestUser::class))->toBeInstanceOf(AuthenticationTestUserPolicy::class);

    expect(Gate::getPolicyFor(static::class))->toBeNull();
});

test('policy can be guessed for parallel class hierarchies', function () {
    expect(Gate::getPolicyFor(Fixtures\Models\Nested\TopTestUser::class))->toBeInstanceOf(TopTestUserPolicy::class);

    expect(Gate::getPolicyFor(Fixtures\Models\Nested\SubTestUser::class))->toBeInstanceOf(SubTestUserPolicy::class);
});

test('policy can be guessed using callback', function () {
    Gate::guessPolicyNamesUsing(function () {
        return AuthenticationTestUserPolicy::class;
    });

    expect(Gate::getPolicyFor(AuthenticationTestUser::class))->toBeInstanceOf(AuthenticationTestUserPolicy::class);
});

test('policy can be guessed multiple times', function () {
    Gate::guessPolicyNamesUsing(function () {
        return [
            'App\\Policies\\TestUserPolicy',
            AuthenticationTestUserPolicy::class,
        ];
    });

    expect(Gate::getPolicyFor(AuthenticationTestUser::class))->toBeInstanceOf(AuthenticationTestUserPolicy::class);
});

test('policy can be given by attribute', function () {
    Gate::guessPolicyNamesUsing(fn () => [AuthenticationTestUserPolicy::class]);

    expect(Gate::getPolicyFor(Post::class))->toBeInstanceOf(PostPolicy::class);
});

test('policy given by attribute is inherited by child classes', function () {
    expect(Gate::getPolicyFor(ChildPost::class))->toBeInstanceOf(PostPolicy::class);
    expect(Gate::getPolicyFor(GrandchildPost::class))->toBeInstanceOf(PostPolicy::class);
});

test('policy given by attribute on child class overrides parent attribute', function () {
    expect(Gate::getPolicyFor(AudioPost::class))->toBeInstanceOf(AudioPostPolicy::class);
});

test('registered policy takes precedence over inherited attribute', function () {
    Gate::policy(ChildPost::class, AudioPostPolicy::class);

    expect(Gate::getPolicyFor(ChildPost::class))->toBeInstanceOf(AudioPostPolicy::class);
});

test('guessed policy takes precedence over inherited attribute', function () {
    Gate::guessPolicyNamesUsing(fn () => [AuthenticationTestUserPolicy::class]);

    expect(Gate::getPolicyFor(ChildPost::class))->toBeInstanceOf(AuthenticationTestUserPolicy::class);
});

#[UsePolicy(PostPolicy::class)]
class Post extends Model
{
}

class ChildPost extends Post
{
}

class GrandchildPost extends ChildPost
{
}

#[UsePolicy(AudioPostPolicy::class)]
class AudioPost extends Post
{
}

class PostPolicy
{
}

class AudioPostPolicy
{
}
