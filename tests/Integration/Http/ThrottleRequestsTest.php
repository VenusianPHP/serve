<?php

namespace Venusian\Serve\Tests\Integration\Http;

use Voyager\Cache\RateLimiter;
use Voyager\Cache\RateLimiting\GlobalLimit;
use Voyager\Cache\RateLimiting\Limit;
use Voyager\Vessel\ControlPanel;
use Voyager\Database\Instrument\Model;
use Mariner\Auth\User;
use Mariner\Http\Exceptions\ThrottleRequestsException;
use Mariner\Http\Request;
use Mariner\Routing\Exceptions\MissingRateLimiterException;
use Mariner\Routing\Middleware\ThrottleRequests;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mariner\NutsAndBolts\Facades\Route;
use Throwable;

beforeEach(fn () => $this->app['config']->set('hashing.driver', 'bcrypt'));

test('lock opens immediately after decay', function () {
    Carbon::setTestNow(Carbon::create(2018, 1, 1, 0, 0, 0));

    Route::get('/', function () {
        return 'yes';
    })->middleware(ThrottleRequests::class.':2,1');

    $response = $this->withoutExceptionHandling()->get('/');
    expect($response->getContent())->toBe('yes');
    expect($response->headers->get('X-RateLimit-Limit'))->toEqual(2);
    expect($response->headers->get('X-RateLimit-Remaining'))->toEqual(1);

    $response = $this->withoutExceptionHandling()->get('/');
    expect($response->getContent())->toBe('yes');
    expect($response->headers->get('X-RateLimit-Limit'))->toEqual(2);
    expect($response->headers->get('X-RateLimit-Remaining'))->toEqual(0);

    Carbon::setTestNow(Carbon::create(2018, 1, 1, 0, 0, 58));

    try {
        $this->withoutExceptionHandling()->get('/');
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(ThrottleRequestsException::class);
        expect($e->getStatusCode())->toEqual(429);
        expect($e->getHeaders()['X-RateLimit-Limit'])->toEqual(2);
        expect($e->getHeaders()['X-RateLimit-Remaining'])->toEqual(0);
        expect($e->getHeaders()['Retry-After'])->toEqual(2);
        expect($e->getHeaders()['X-RateLimit-Reset'])->toEqual(Carbon::now()->addSeconds(2)->getTimestamp());
    }
});

test('limiting using named limiter', function () {
    $rateLimiter = ControlPanel::getInstance()->make(RateLimiter::class);

    $rateLimiter->for('test', function ($request) {
        return new GlobalLimit(2, 1);
    });

    Carbon::setTestNow(Carbon::create(2018, 1, 1, 0, 0, 0));

    Route::get('/', function () {
        return 'yes';
    })->middleware(ThrottleRequests::class.':test');

    $response = $this->withoutExceptionHandling()->get('/');
    expect($response->getContent())->toBe('yes');
    expect($response->headers->get('X-RateLimit-Limit'))->toEqual(2);
    expect($response->headers->get('X-RateLimit-Remaining'))->toEqual(1);

    $response = $this->withoutExceptionHandling()->get('/');
    expect($response->getContent())->toBe('yes');
    expect($response->headers->get('X-RateLimit-Limit'))->toEqual(2);
    expect($response->headers->get('X-RateLimit-Remaining'))->toEqual(0);

    Carbon::setTestNow(Carbon::create(2018, 1, 1, 0, 0, 58));

    try {
        $this->withoutExceptionHandling()->get('/');
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(ThrottleRequestsException::class);
        expect($e->getStatusCode())->toEqual(429);
        expect($e->getHeaders()['X-RateLimit-Limit'])->toEqual(2);
        expect($e->getHeaders()['X-RateLimit-Remaining'])->toEqual(0);
        expect($e->getHeaders()['Retry-After'])->toEqual(2);
        expect($e->getHeaders()['X-RateLimit-Reset'])->toEqual(Carbon::now()->addSeconds(2)->getTimestamp());
    }
});

test('it can generate definition via static method', function () {
    $signature = (string) ThrottleRequests::using('gold-tier');
    expect($signature)->toBe('Mariner\Routing\Middleware\ThrottleRequests:gold-tier');

    $signature = (string) ThrottleRequests::with(25);
    expect($signature)->toBe('Mariner\Routing\Middleware\ThrottleRequests:25');

    $signature = (string) ThrottleRequests::with(25, 2);
    expect($signature)->toBe('Mariner\Routing\Middleware\ThrottleRequests:25,2');

    $signature = (string) ThrottleRequests::with(25, 2, 'foo');
    expect($signature)->toBe('Mariner\Routing\Middleware\ThrottleRequests:25,2,foo');

    $signature = (string) ThrottleRequests::with(maxAttempts: 25, decayMinutes: 2, prefix: 'foo');
    expect($signature)->toBe('Mariner\Routing\Middleware\ThrottleRequests:25,2,foo');

    $signature = (string) ThrottleRequests::with(prefix: 'foo');
    expect($signature)->toBe('Mariner\Routing\Middleware\ThrottleRequests:60,1,foo');
});

dataset('perMinuteThrottlingDataSet', function () {
    return [
        [ThrottleRequests::using('test')],
        [ThrottleRequests::with(maxAttempts: 3, decayMinutes: 1)],
        ['throttle:3,1'],
    ];
});

test('it can throttle per minute', function (string $middleware) {
    $rateLimiter = ControlPanel::getInstance()->make(RateLimiter::class);
    $rateLimiter->for('test', fn () => Limit::perMinute(3));
    Route::get('/', fn () => 'ok')->middleware($middleware);

    Carbon::setTestNow('2000-01-01 00:00:00.000');

    // Make 3 requests, each a second apart, that should all be successful.

    for ($i = 0; $i < 3; $i++) {
        match ($i) {
            0 => expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:00.000'),
            1 => expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:01.000'),
            2 => expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:02.000'),
        };

        $response = $this->get('/');
        $response->assertOk();
        $response->assertContent('ok');
        $response->assertHeader('X-RateLimit-Limit', 3);
        $response->assertHeader('X-RateLimit-Remaining', 3 - ($i + 1));

        Carbon::setTestNow(Carbon::now()->addSecond());
    }

    // It is now 3 seconds past and we will make another request that
    // should be rate limited.

    expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:03.000');

    $response = $this->get('/');
    $response->assertStatus(429);
    $response->assertHeader('Retry-After', 57);
    $response->assertHeader('X-RateLimit-Reset', Carbon::now()->addSeconds(57)->getTimestamp());
    $response->assertHeader('X-RateLimit-Limit', 3);
    $response->assertHeader('X-RateLimit-Remaining', 0);

    // We will now make it the very end of the minute, to check boundaries,
    // and make another request that should be rate limited and tell us to
    // try again in 1 second.
    Carbon::setTestNow('2000-01-01 00:00:59.999');

    $response = $this->get('/');
    $response->assertStatus(429);
    $response->assertHeader('Retry-After', 1);
    $response->assertHeader('X-RateLimit-Reset', Carbon::now()->addSecond()->getTimestamp());
    $response->assertHeader('X-RateLimit-Limit', 3);
    $response->assertHeader('X-RateLimit-Remaining', 0);

    // We now tick over into the next second. We should now be able to make
    // requests again.
    Carbon::setTestNow('2000-01-01 00:01:00.000');

    $response = $this->get('/');
    $response->assertOk();
})->with('perMinuteThrottlingDataSet');

test('it can throttle per second', function () {
    $rateLimiter = ControlPanel::getInstance()->make(RateLimiter::class);
    $rateLimiter->for('test', fn () => Limit::perSecond(3));
    Route::get('/', fn () => 'ok')->middleware(ThrottleRequests::using('test'));

    Carbon::setTestNow('2000-01-01 00:00:00.000');

    // Make 3 requests, each a 100ms apart, that should all be successful.

    for ($i = 0; $i < 3; $i++) {
        match ($i) {
            0 => expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:00.000'),
            1 => expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:00.100'),
            2 => expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:00.200'),
        };

        $response = $this->get('/');
        $response->assertOk();
        $response->assertContent('ok');
        $response->assertHeader('X-RateLimit-Limit', 3);
        $response->assertHeader('X-RateLimit-Remaining', 3 - ($i + 1));

        Carbon::setTestNow(Carbon::now()->addMilliseconds(100));
    }

    // It is now 300 milliseconds past and we will make another request
    // that should be rate limited.

    expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:00.300');

    $response = $this->get('/');
    $response->assertStatus(429);
    $response->assertHeader('Retry-After', 1);
    $response->assertHeader('X-RateLimit-Reset', Carbon::now()->addSecond()->getTimestamp());
    $response->assertHeader('X-RateLimit-Limit', 3);
    $response->assertHeader('X-RateLimit-Remaining', 0);

    // We will now make it the very end of the minute, to check boundaries,
    // and make another request that should be rate limited and tell us to
    // try again in 1 second.
    Carbon::setTestNow('2000-01-01 00:00:00.999');

    $response = $this->get('/');
    $response->assertHeader('Retry-After', 1);
    $response->assertHeader('X-RateLimit-Reset', Carbon::now()->addSecond()->getTimestamp());
    $response->assertHeader('X-RateLimit-Limit', 3);
    $response->assertHeader('X-RateLimit-Remaining', 0);

    // We now tick over into the next second. We should now be able to make
    // requests again.
    Carbon::setTestNow('2000-01-01 00:00:01.000');

    $response = $this->get('/');
    $response->assertOk();
});

test('it can combine rate limits without specifying unique keys', function () {
    $rateLimiter = ControlPanel::getInstance()->make(RateLimiter::class);
    $rateLimiter->for('test', fn () => [
        Limit::perSecond(3),
        Limit::perMinute(5),
    ]);
    Route::get('/', fn () => 'ok')->middleware(ThrottleRequests::using('test'));

    Carbon::setTestNow('2000-01-01 00:00:00.000');

    // Make 3 requests, each a 100ms apart, that should all be successful.

    for ($i = 0; $i < 3; $i++) {
        match ($i) {
            0 => expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:00.000'),
            1 => expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:00.100'),
            2 => expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:00.200'),
        };

        $response = $this->get('/');
        $response->assertOk();
        $response->assertContent('ok');

        Carbon::setTestNow(Carbon::now()->addMilliseconds(100));
    }

    // It is now 300 milliseconds past and we will make another request
    // that should be rate limited.

    expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:00.300');

    $response = $this->get('/');
    $response->assertStatus(429);
    $response->assertHeader('Retry-After', 1);
    $response->assertHeader('X-RateLimit-Reset', Carbon::now()->addSecond()->getTimestamp());
    $response->assertHeader('X-RateLimit-Limit', 3);
    $response->assertHeader('X-RateLimit-Remaining', 0);

    // We will now make it the very end of the second, to check boundaries,
    // and make another request that should be rate limited and tell us to
    // try again in 1 second.
    Carbon::setTestNow('2000-01-01 00:00:00.999');

    $response = $this->get('/');
    $response->assertHeader('Retry-After', 1);
    $response->assertHeader('X-RateLimit-Reset', Carbon::now()->addSecond()->getTimestamp());
    $response->assertHeader('X-RateLimit-Limit', 3);
    $response->assertHeader('X-RateLimit-Remaining', 0);

    // We now tick over into the next second. We should now be able to make
    // another two requests before the per minute rate limit kicks in.
    Carbon::setTestNow('2000-01-01 00:00:01.000');

    for ($i = 0; $i < 2; $i++) {
        match ($i) {
            0 => expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:01.000'),
            1 => expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:01.100'),
        };

        $response = $this->get('/');
        $response->assertOk();
        $response->assertContent('ok');

        Carbon::setTestNow(Carbon::now()->addMilliseconds(100));
    }

    // The per minute rate limiter should now fail.

    expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:01.200');

    $response = $this->get('/');
    $response->assertStatus(429);
    $response->assertHeader('Retry-After', 59);
    $response->assertHeader('X-RateLimit-Reset', Carbon::now()->addSeconds(59)->getTimestamp());
    $response->assertHeader('X-RateLimit-Limit', 5);
    $response->assertHeader('X-RateLimit-Remaining', 0);
});

test('it fails if named limiter does not exist', function () {

    Route::get('/', fn () => 'ok')->middleware(ThrottleRequests::using('test'));

    $this->withoutExceptionHandling()->get('/');
})->throws(MissingRateLimiterException::class, 'Rate limiter [test] is not defined.');

test('it fallbacks to user property when there is no named limiter when authenticated', function () {
    $user = User::make()->forceFill([
        'rateLimiting' => 1,
    ]);

    Carbon::setTestNow(Carbon::create(2018, 1, 1, 0, 0, 0));

    // The `rateLimiting` named limiter does not exist, but the `rateLimiting` property on the
    // User model does, so it should fallback to that property within the authenticated model.
    Route::get('/', fn () => 'yes')->middleware(['auth', ThrottleRequests::using('rateLimiting')]);

    $response = $this->withoutExceptionHandling()->actingAs($user)->get('/');
    expect($response->getContent())->toBe('yes');
    expect($response->headers->get('X-RateLimit-Limit'))->toEqual(1);
    expect($response->headers->get('X-RateLimit-Remaining'))->toEqual(0);

    Carbon::setTestNow(Carbon::create(2018, 1, 1, 0, 0, 58));

    try {
        $this->withoutExceptionHandling()->actingAs($user)->get('/');
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(ThrottleRequestsException::class);
        expect($e->getStatusCode())->toEqual(429);
        expect($e->getHeaders()['X-RateLimit-Limit'])->toEqual(1);
        expect($e->getHeaders()['X-RateLimit-Remaining'])->toEqual(0);
        expect($e->getHeaders()['Retry-After'])->toEqual(2);
        expect($e->getHeaders()['X-RateLimit-Reset'])->toEqual(Carbon::now()->addSeconds(2)->getTimestamp());
    }
});

test('it fallbacks to user accessor when there is no named limiter when authenticated', function () {
    $user = UserWithAccessor::make();

    Carbon::setTestNow(Carbon::create(2018, 1, 1, 0, 0, 0));

    // The `rateLimiting` named limiter does not exist, but the `rateLimiting` accessor (not property!)
    // on the User model does, so it should fallback to that accessor within the authenticated model.
    Route::get('/', fn () => 'yes')->middleware(['auth', ThrottleRequests::using('rateLimiting')]);

    $response = $this->withoutExceptionHandling()->actingAs($user)->get('/');
    expect($response->getContent())->toBe('yes');
    expect($response->headers->get('X-RateLimit-Limit'))->toEqual(1);
    expect($response->headers->get('X-RateLimit-Remaining'))->toEqual(0);

    Carbon::setTestNow(Carbon::create(2018, 1, 1, 0, 0, 58));

    try {
        $this->withoutExceptionHandling()->actingAs($user)->get('/');
    } catch (Throwable $e) {
        expect($e)->toBeInstanceOf(ThrottleRequestsException::class);
        expect($e->getStatusCode())->toEqual(429);
        expect($e->getHeaders()['X-RateLimit-Limit'])->toEqual(1);
        expect($e->getHeaders()['X-RateLimit-Remaining'])->toEqual(0);
        expect($e->getHeaders()['Retry-After'])->toEqual(2);
        expect($e->getHeaders()['X-RateLimit-Reset'])->toEqual(Carbon::now()->addSeconds(2)->getTimestamp());
    }
});

test('multiple distinct keys do not over throttle', function () {
    $rateLimiter = ControlPanel::getInstance()->make(RateLimiter::class);
    $rateLimiter->for('test', fn () => [
        Limit::perMinute(3)->by('minute-key'),
        Limit::perSecond(1)->by('second-key'),
        Limit::perDay(4)->by('day-key'),
    ]);
    Route::get('/', fn () => 'ok')->middleware(ThrottleRequests::using('test'));

    // Running 2 requests in a single second is not allowed.
    Carbon::setTestNow('2000-01-01 00:00:00.000');
    $this->get('/')->assertOk();
    $this->get('/')->assertStatus(429);

    // After a second, the per-second limit should resets.
    Carbon::setTestNow('2000-01-01 00:00:01.000');
    $this->get('/')->assertOk();
    $this->get('/')->assertStatus(429);

    // A third request is allowed in the same minute.
    Carbon::setTestNow('2000-01-01 00:00:02.000');
    $this->get('/')->assertOk();

    // A fourth request is not allowed in the same minute.
    Carbon::setTestNow('2000-01-01 00:00:03.000');
    $this->get('/')->assertStatus(429);

    // A fourth request is allowed in the same day, but not a fifth.
    Carbon::setTestNow('2000-01-01 01:00:00.000');
    $this->get('/')->assertOk();
    $this->get('/')->assertStatus(429);

    // The next day, all limits should reset.
    Carbon::setTestNow('2000-01-02 00:01:00.000');
    $this->get('/')->assertOk();
    Carbon::setTestNow('2000-01-02 00:01:01.000');
    $this->get('/')->assertOk();
    Carbon::setTestNow('2000-01-02 00:01:02.000');
    $this->get('/')->assertOk();
    Carbon::setTestNow('2000-01-02 00:01:03.000');
    $this->get('/')->assertStatus(429);
    Carbon::setTestNow('2000-01-02 01:00:00.000');
    $this->get('/')->assertOk();
    $this->get('/')->assertStatus(429);
});

test('limit order does not affect behavior', function () {
    $rateLimiter = ControlPanel::getInstance()->make(RateLimiter::class);
    $rateLimiter->for('test', fn () => [
        Limit::perDay(4)->by('day-key'),
        Limit::perMinute(3)->by('minute-key'),
    ]);
    Route::get('/', fn () => 'ok')->middleware(ThrottleRequests::using('test'));

    Carbon::setTestNow('2000-01-01 00:00:00.000');

    // Make 3 requests, each a second apart, that should all be successful.
    for ($i = 0; $i < 3; $i++) {
        $this->get('/')->assertOk();
        Carbon::setTestNow(Carbon::now()->addSecond());
    }

    expect(Carbon::now()->toDateTimeString('m'))->toBe('2000-01-01 00:00:03.000');
    $this->get('/')->assertStatus(429);

    // A fourth request is allowed in the same day but not a fifth.
    Carbon::setTestNow('2000-01-01 00:01:00.000');
    $this->get('/')->assertOk();
    $this->get('/')->assertStatus(429);

    // After a day, both limits should reset.
    Carbon::setTestNow('2000-01-02 00:00:00.000');
    $this->get('/')->assertOk();
});

test('it can throttle based on response', function () {
    app(RateLimiter::class)->for('throttle-not-found', function (Request $request) {
        return Limit::perMinute(1)->after(fn ($response) => $response->status() === 404);
    });
    Route::get('/', fn () => match (request('status')) {
        '404' => abort(404),
        default => 'ok',
    })->middleware(ThrottleRequests::using('throttle-not-found'));

    $this->travelTo('2000-01-01 00:00:00');
    $this->get('?status=404')->assertNotFound();
    $this->get('?status=404')->assertTooManyRequests();
    $this->get('?status=404')->assertTooManyRequests();

    $this->travelTo('2000-01-01 00:00:59');
    $this->get('?status=404')->assertTooManyRequests();
    $this->get('?status=404')->assertTooManyRequests();

    $this->travelTo('2000-01-01 00:01:00');
    $this->get('?status=404')->assertNotFound();
    $this->get('?status=404')->assertTooManyRequests();
    $this->get('?status=404')->assertTooManyRequests();

    $this->travelTo('2000-01-01 00:01:59');
    $this->get('?status=404')->assertTooManyRequests();
    $this->get('?status=404')->assertTooManyRequests();

    $this->travelTo('2000-01-01 00:02:00');
    $this->get('?status=404')->assertNotFound();
    $this->get('?status=404')->assertTooManyRequests();
    $this->get('?status=404')->assertTooManyRequests();
});

test('it does not hit limiter until response has been generated', function () {
    ThrottleRequests::shouldHashKeys(false);
    app(RateLimiter::class)->for('throttle-not-found', function (Request $request) {
        return Limit::perMinute(1)->after(fn ($response) => $response->status() === 404);
    });
    $duringRequest = null;
    Route::get('/', function () use (&$duringRequest) {
        $duringRequest = [
            app('cache')->get('throttle-not-found:'),
            app('cache')->get('throttle-not-found::timer'),
        ];

        abort(404);
    })->middleware(ThrottleRequests::using('throttle-not-found'));

    $this->travelTo('2000-01-01 00:00:00');
    $this->get('?status=404')->assertNotFound();

    expect($duringRequest)->toBe([null, null]);
    expect([
        app('cache')->get('throttle-not-found:'),
        app('cache')->get('throttle-not-found::timer'),
    ])->toBe([1, 946684860]);
});

test('it returns configured response when using after limit', function () {
    ThrottleRequests::shouldHashKeys(false);
    app(RateLimiter::class)->for('throttle-not-found', function (Request $request) {
        return Limit::perMinute(1)
            ->after(fn ($response) => $response->status() === 404)
            ->response(fn () => response('ah ah ah', status: 429));
    });
    Route::get('/', fn () => abort(404))->middleware(ThrottleRequests::using('throttle-not-found'));

    $this->travelTo('2000-01-01 00:00:00');
    $this->get('?status=404')->assertNotFound();
    $this->get('?status=404')->assertTooManyRequests()->assertContent('ah ah ah');
});

class UserWithAccessor extends User
{
    public function getRateLimitingAttribute(): int
    {
        return 1;
    }
}
