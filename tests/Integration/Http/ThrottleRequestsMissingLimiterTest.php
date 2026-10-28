<?php

namespace Venusian\Serve\Tests\Integration\Http;

use Mariner\Auth\User;
use Mariner\Routing\Exceptions\MissingRateLimiterException;
use Mariner\Routing\Middleware\ThrottleRequests;
use Mariner\NutsAndBolts\Facades\Route;
use Venusian\Serve\Tests\Integration\IntegrationTestCase;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeAll(fn () => IntegrationTestCase::withMigrations('laravel'));
afterAll(fn () => IntegrationTestCase::withMigrations());

beforeEach(fn () => $this->app['config']->set('hashing.driver', 'bcrypt'));
afterEach(fn () => Model::shouldBeStrict(false));

test('it fails if named limiter does not exist and authenticated user does not have fallback property', function () {
    Route::get('/', fn () => 'ok')->middleware(['auth', ThrottleRequests::using('rateLimiting')]);

    // The reason we're enabling strict mode and actually creating a user is to ensure we never even try to access
    // a property within the user model that does not exist. If an application is in strict mode and there is
    // no matching rate limiter, it should throw a rate limiter exception, not a property access exception.
    Model::shouldBeStrict();
    $user = User::forceCreate([
        'name' => 'Mateus',
        'email' => 'mateus@example.org',
        'password' => 'password',
    ]);

    expect(fn () => $this->withoutExceptionHandling()->actingAs($user)->get('/'))
        ->toThrow(MissingRateLimiterException::class, 'Rate limiter ['.User::class.'::rateLimiting] is not defined.');
});
