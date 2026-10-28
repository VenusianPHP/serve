<?php

namespace Venusian\Serve\Tests\Integration\Auth;

use Voyager\Database\QueryException;
use Mariner\NutsAndBolts\Facades\Route;
use Voyager\NutsAndBolts\Str;

beforeEach(function () {
    $app = $this->app;
    // Auth configuration
    $app['config']->set('auth.defaults.guard', 'api');

    $app['config']->set('auth.guards.api', [
        'driver' => 'token',
        'provider' => 'users',
        'hash' => false,
    ]);

    // Database configuration
    $app['config']->set('database.default', 'testbench');

    $app['config']->set('database.connections.testbench', [
        'driver' => 'mysql',
        'host' => env('DB_HOST', '127.0.0.1'),
        'username' => 'root',
        'password' => 'invalid-credentials',
        'database' => 'forge',
        'prefix' => '',
    ]);
});

test('authentication via api with eloquent using wrong database credentials should not cause infinite loop', function () {
    Route::get('/auth', function () {
        return 'success';
    })->middleware('auth:api');

    try {
        $this->withoutExceptionHandling()->get('/auth', ['Authorization' => 'Bearer whatever']);
    } catch (QueryException $e) {
        if (Str::startsWith($e->getMessage(), 'SQLSTATE[HY000] [2002]')) {
            $this->markTestSkipped('MySQL instance required.');
        }

        throw $e;
    }
})->throws(QueryException::class, "Access denied for user 'root'@");
