<?php

namespace Venusian\Serve\Tests\Integration\Auth\Middleware;

use Mariner\Auth\Middleware\RedirectIfAuthenticated;
use Mariner\Contracts\Routing\Registrar;
use Voyager\NutsAndBolts\Str;
use Venusian\Serve\Tests\Integration\Auth\Fixtures\AuthenticationTestUser;
use Voyager\Database\Testing\RefreshDatabase;
use Venusian\Serve\Tests\Integration\Fixtures\UserFactory;
use Venusian\Serve\Tests\Integration\IntegrationTestCase;

uses(RefreshDatabase::class);

beforeAll(fn () => IntegrationTestCase::withMigrations('laravel'));
afterAll(fn () => IntegrationTestCase::withMigrations());

beforeEach(function () {
    $app = $this->app;
    $app['config']->set('app.key', Str::random(32));
    $app['config']->set('auth.providers.users.model', AuthenticationTestUser::class);
});

beforeEach(function () {

    $this->withoutExceptionHandling();

    /** @var \Mariner\Contracts\Routing\Registrar $router */
    $this->router = $this->app->make(Registrar::class);

    $this->router->get('/login', function () {
        return response('Login Form');
    })->middleware(RedirectIfAuthenticated::class);

    UserFactory::new()->create();

    $user = AuthenticationTestUser::first();
    $this->router->get('/login', function () {
        return response('Login Form');
    })->middleware(RedirectIfAuthenticated::class);

    UserFactory::new()->create();

    $this->user = AuthenticationTestUser::first();
});

test('when dashboard named route is available', function () {
    $this->router->get('/named-dashboard', function () {
        return response('Named Dashboard');
    })->name('dashboard');

    $response = $this->actingAs($this->user)->get('/login');

    $response->assertRedirect('/named-dashboard');
});

test('when home named route is available', function () {
    $this->router->get('/named-home', function () {
        return response('Named Home');
    })->name('home');

    $response = $this->actingAs($this->user)->get('/login');

    $response->assertRedirect('/named-home');
});

test('when dashboard slug is available', function () {
    $this->router->get('/dashboard', function () {
        return response('My Dashboard');
    });

    $response = $this->actingAs($this->user)->get('/login');

    $response->assertRedirect('/dashboard');
});

test('when home slug is available', function () {
    $this->router->get('/home', function () {
        return response('My Home');
    })->name('home');

    $response = $this->actingAs($this->user)->get('/login');

    $response->assertRedirect('/home');
});

test('when home or dashboard are not available', function () {
    $response = $this->actingAs($this->user)->get('/login');

    $response->assertRedirect('/');
});

test('when guest', function () {
    $response = $this->get('/login');

    $response->assertOk();
    $response->assertSee('Login Form');
});
