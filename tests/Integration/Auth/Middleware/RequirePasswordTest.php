<?php

namespace Venusian\Serve\Tests\Integration\Auth\Middleware;

use Mariner\Auth\Middleware\RequirePassword;
use Voyager\Contracts\Config\Repository;
use Mariner\Core\Http\Kernel as HttpKernel;
use Mariner\Contracts\Routing\Registrar;
use Mariner\Contracts\Routing\UrlGenerator;
use Mariner\Http\Middleware\PrefersJsonResponses;
use Mariner\Http\Response;
use Mariner\Session\Middleware\StartSession;

test('it can generate definition via static method', function () {
    $signature = (string) RequirePassword::using('route.name');
    expect($signature)->toBe('Mariner\Auth\Middleware\RequirePassword:route.name');

    $signature = (string) RequirePassword::using('route.name', 100);
    expect($signature)->toBe('Mariner\Auth\Middleware\RequirePassword:route.name,100');

    $signature = (string) RequirePassword::using(passwordTimeoutSeconds: 100);
    expect($signature)->toBe('Mariner\Auth\Middleware\RequirePassword:,100');
});

test('user sees the wanted page if the password was recently confirmed', function () {
    $this->withoutExceptionHandling();

    /** @var \Mariner\Contracts\Routing\Registrar $router */
    $router = $this->app->make(Registrar::class);

    $router->get('test-route', function (): Response {
        return new Response('foobar');
    })->middleware([StartSession::class, RequirePassword::class]);

    $response = $this->withSession(['auth.password_confirmed_at' => time()])->get('test-route');

    $response->assertOk();
    $response->assertSeeText('foobar');
});

test('user is redirected to the password confirm route if the password was not recently confirmed', function () {
    $this->withoutExceptionHandling();

    /** @var \Mariner\Contracts\Routing\Registrar $router */
    $router = $this->app->make(Registrar::class);

    $router->get('password-confirm', function (): Response {
        return new Response('foo');
    })->name('password.confirm');

    $router->get('test-route', function (): Response {
        return new Response('foobar');
    })->middleware([StartSession::class, RequirePassword::class]);

    $response = $this->withSession(['auth.password_confirmed_at' => time() - 10801])->get('test-route');

    $response->assertStatus(302);
    $response->assertRedirect($this->app->make(UrlGenerator::class)->route('password.confirm'));
});

test('user is redirected to acustom route if the password was not recently confirmed and the custom route is specified', function () {
    $this->withoutExceptionHandling();

    /** @var \Mariner\Contracts\Routing\Registrar $router */
    $router = $this->app->make(Registrar::class);

    $router->get('confirm', function (): Response {
        return new Response('foo');
    })->name('my-password.confirm');

    $router->get('test-route', function (): Response {
        return new Response('foobar');
    })->middleware([StartSession::class, RequirePassword::class.':my-password.confirm']);

    $response = $this->withSession(['auth.password_confirmed_at' => time() - 10801])->get('test-route');

    $response->assertStatus(302);
    $response->assertRedirect($this->app->make(UrlGenerator::class)->route('my-password.confirm'));
});

test('prefers json returns json response for wildcard accept instead of redirecting', function () {
    $this->withoutExceptionHandling();

    $this->app->make(HttpKernel::class)->prependMiddleware(PrefersJsonResponses::class);

    /** @var \Mariner\Contracts\Routing\Registrar $router */
    $router = $this->app->make(Registrar::class);

    $router->get('test-route', function (): Response {
        return new Response('foobar');
    })->middleware([StartSession::class, RequirePassword::class]);

    $response = $this->withSession(['auth.password_confirmed_at' => time() - 10801])
        ->get('test-route', ['Accept' => '*/*']);

    $response->assertStatus(423);
    $response->assertJson(['message' => 'Password confirmation required.']);
});

test('prefers json still redirects when accept is explicit html', function () {
    $this->withoutExceptionHandling();

    $this->app->make(HttpKernel::class)->prependMiddleware(PrefersJsonResponses::class);

    /** @var \Mariner\Contracts\Routing\Registrar $router */
    $router = $this->app->make(Registrar::class);

    $router->get('password-confirm', function (): Response {
        return new Response('foo');
    })->name('password.confirm');

    $router->get('test-route', function (): Response {
        return new Response('foobar');
    })->middleware([StartSession::class, RequirePassword::class]);

    $response = $this->withSession(['auth.password_confirmed_at' => time() - 10801])
        ->get('test-route', ['Accept' => 'text/html']);

    $response->assertRedirect($this->app->make(UrlGenerator::class)->route('password.confirm'));
});

test('auth password timeout is configurable', function () {
    $this->withoutExceptionHandling();

    /** @var \Mariner\Contracts\Routing\Registrar $router */
    $router = $this->app->make(Registrar::class);

    $router->get('password-confirm', function (): Response {
        return new Response('foo');
    })->name('password.confirm');

    $router->get('test-route', function (): Response {
        return new Response('foobar');
    })->middleware([StartSession::class, RequirePassword::class]);

    $this->app->make(Repository::class)->set('auth.password_timeout', 500);

    $response = $this->withSession(['auth.password_confirmed_at' => time() - 495])->get('test-route');

    $response->assertOk();
    $response->assertSeeText('foobar');

    $response = $this->withSession(['auth.password_confirmed_at' => time() - 501])->get('test-route');

    $response->assertStatus(302);
    $response->assertRedirect($this->app->make(UrlGenerator::class)->route('password.confirm'));
});
