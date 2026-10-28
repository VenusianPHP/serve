<?php

namespace Venusian\Serve\Tests\Integration\Http;

use Voyager\Cache\RateLimiter;
use Voyager\Cache\RateLimiting\Limit;
use Mariner\Http\Request;
use Mariner\Routing\Middleware\ThrottleRequestsWithRedis;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mariner\NutsAndBolts\Facades\Route;
use Throwable;
use Voyager\Contracts\Redis\Factory as RedisFactory;
use Voyager\Redis\RedisServiceProvider;

// These tests need a Redis server. Where none answers they skip, and CI runs them against a Redis service.
beforeEach(function () {
    $host = getenv('REDIS_HOST') ?: '127.0.0.1';
    $port = (int) (getenv('REDIS_PORT') ?: 6379);

    $socket = @fsockopen($host, $port, $errno, $error, 1);

    if ($socket === false) {
        $this->markTestSkipped("Redis is not reachable at {$host}:{$port}.");
    }

    fclose($socket);

    $this->app['config']->set('hashing.driver', '');
    $this->app['config']->set('database.redis', [
        'client' => 'phpredis',
        // A key prefix of this test's own, never a flush: the server may be shared with other work.
        'options' => ['prefix' => 'serve-test-'.bin2hex(random_bytes(4)).':'],
        'default' => ['host' => $host, 'port' => $port, 'database' => 15],
    ]);
    $this->app->register(RedisServiceProvider::class);
    $this->app->registerInstance(RedisFactory::class, $this->app['redis']);
});

test('lock opens immediately after decay', function () {
    $now = Carbon::now();

    Carbon::setTestNow($now);

    Route::get('/', function () {
        return 'yes';
    })->middleware(ThrottleRequestsWithRedis::class.':2,1');

    $response = $this->withoutExceptionHandling()->get('/');
    expect($response->getContent())->toBe('yes');
    expect($response->headers->get('X-RateLimit-Limit'))->toEqual(2);
    expect($response->headers->get('X-RateLimit-Remaining'))->toEqual(1);

    $response = $this->withoutExceptionHandling()->get('/');
    expect($response->getContent())->toBe('yes');
    expect($response->headers->get('X-RateLimit-Limit'))->toEqual(2);
    expect($response->headers->get('X-RateLimit-Remaining'))->toEqual(0);

    Carbon::setTestNow($finish = $now->addSeconds(58));

    try {
        $this->withoutExceptionHandling()->get('/');
    } catch (Throwable $e) {
        expect($e->getStatusCode())->toEqual(429);
        expect($e->getHeaders()['X-RateLimit-Limit'])->toEqual(2);
        expect($e->getHeaders()['X-RateLimit-Remaining'])->toEqual(0);
        // $this->assertTrue(in_array($e->getHeaders()['Retry-After'], [2, 3]));
        // $this->assertTrue(in_array($e->getHeaders()['X-RateLimit-Reset'], [$finish->getTimestamp() + 2, $finish->getTimestamp() + 3]));
    }
});

test('it can throttle based on response', function () {
    $this->app->make(RateLimiter::class)->for('throttle-not-found', function (Request $request) {
        return Limit::perMinute(1)->after(fn ($response) => $response->status() === 404);
    });
    Route::get('/', fn () => match (request('status')) {
        '404' => abort(404),
        default => 'ok',
    })->middleware(ThrottleRequestsWithRedis::using('throttle-not-found'));

    // Non-matching responses should not count toward the limit.
    $this->get('?status=200')->assertOk();
    $this->get('?status=200')->assertOk();
    $this->get('?status=200')->assertOk();

    // A matching response should count and exhaust the limit.
    $this->get('?status=404')->assertNotFound();

    // Now throttled — even non-matching requests are blocked.
    $this->get('?status=200')->assertTooManyRequests();
    $this->get('?status=404')->assertTooManyRequests();
});

test('it returns configured response when using after limit', function () {
    $this->app->make(RateLimiter::class)->for('throttle-not-found', function (Request $request) {
        return Limit::perMinute(1)
            ->after(fn ($response) => $response->status() === 404)
            ->response(fn () => response('ah ah ah', status: 429));
    });
    Route::get('/', fn () => abort(404))->middleware(ThrottleRequestsWithRedis::using('throttle-not-found'));

    $this->get('/')->assertNotFound();
    $this->get('/')->assertTooManyRequests()->assertContent('ah ah ah');
});

test('it returns remaining attempts header when response is not counted', function () {
    $this->app->make(RateLimiter::class)->for('throttle-not-found', function (Request $request) {
        return Limit::perMinute(5)->after(fn ($response) => $response->status() === 404);
    });
    Route::get('/', fn () => 'ok')->middleware(ThrottleRequestsWithRedis::using('throttle-not-found'));

    $this->get('/')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', 5)
        ->assertHeader('X-RateLimit-Remaining', 5);
});
