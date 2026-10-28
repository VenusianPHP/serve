<?php

namespace Venusian\Serve\Tests\Integration\Http;

use Carbon\CarbonInterval;
use Mariner\Core\Http\Kernel;
use Mariner\Http\Request;
use Mariner\Http\Response;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mariner\NutsAndBolts\Facades\Route;

test('it can handle exceeding request duration', function () {
    Route::get('test-route', fn () => 'ok');
    $request = Request::create('http://localhost/test-route');
    $response = new Response();
    $called = false;
    $kernel = $this->app[Kernel::class];
    $kernel->whenRequestLifecycleIsLongerThan(CarbonInterval::second(), function () use (&$called) {
        $called = true;
    });

    Carbon::setTestNow($now = Carbon::now());
    $kernel->handle($request);

    Carbon::setTestNow($now->addSecond()->addMillisecond());
    $kernel->terminate($request, $response);

    expect($called)->toBeTrue();
});

test('it doesnt call when exactly threshold duration', function () {
    Route::get('test-route', fn () => 'ok');
    $request = Request::create('http://localhost/test-route');
    $response = new Response();
    $called = false;
    $kernel = $this->app[Kernel::class];
    $kernel->whenRequestLifecycleIsLongerThan(CarbonInterval::second(), function () use (&$called) {
        $called = true;
    });

    Carbon::setTestNow($now = Carbon::now());
    $kernel->handle($request);

    Carbon::setTestNow($now->addSecond());
    $kernel->terminate($request, $response);

    expect($called)->toBeFalse();
});

test('it provides request to handler', function () {
    Route::get('test-route', fn () => 'ok');
    $request = Request::create('http://localhost/test-route');
    $response = new Response();
    $url = null;
    $kernel = $this->app[Kernel::class];
    $kernel->whenRequestLifecycleIsLongerThan(CarbonInterval::second(), function ($startedAt, $request) use (&$url) {
        $url = $request->url();
    });

    Carbon::setTestNow($now = Carbon::now());
    $kernel->handle($request);

    Carbon::setTestNow($now->addSeconds(2));
    $kernel->terminate($request, $response);

    expect($url)->toBe('http://localhost/test-route');
});

test('uses the configured date timezone', function () {
    app('config')->set('app.timezone', 'UTC');
    Route::get('test-route', fn () => 'ok');
    $kernel = $this->app[Kernel::class];
    $startedAt = null;
    $kernel->whenRequestLifecycleIsLongerThan(CarbonInterval::second(), function ($started) use (&$startedAt) {
        $startedAt = $started;
    });

    app('config')->set('app.timezone', 'Australia/Melbourne');
    Carbon::setTestNow($now = Carbon::today());
    $kernel->handle($request = Request::create('http://localhost/test-route'));
    Carbon::setTestNow($now->addMinute());
    $kernel->terminate($request, new Response);

    expect($startedAt->timezone->getName())->toBe('Australia/Melbourne');
});

test('it can exceed threshold when specifying duration as milliseconds', function () {
    Route::get('test-route', fn () => 'ok');
    $request = Request::create('http://localhost/test-route');
    $response = new Response();
    $called = false;
    $kernel = $this->app[Kernel::class];
    $kernel->whenRequestLifecycleIsLongerThan(1000, function () use (&$called) {
        $called = true;
    });

    Carbon::setTestNow($now = Carbon::now());
    $kernel->handle($request);

    Carbon::setTestNow($now->addSecond()->addMillisecond());
    $kernel->terminate($request, $response);

    expect($called)->toBeTrue();
});

test('it can stay under threshold when specifying duration as milliseconds', function () {
    Route::get('test-route', fn () => 'ok');
    $request = Request::create('http://localhost/test-route');
    $response = new Response();
    $called = false;
    $kernel = $this->app[Kernel::class];
    $kernel->whenRequestLifecycleIsLongerThan(1000, function () use (&$called) {
        $called = true;
    });

    Carbon::setTestNow($now = Carbon::now());
    $kernel->handle($request);

    Carbon::setTestNow($now->addSecond());
    $kernel->terminate($request, $response);

    expect($called)->toBeFalse();
});

test('it can exceed threshold when specifying duration as date time', function () {
    Route::get('test-route', fn () => 'ok');
    $request = Request::create('http://localhost/test-route');
    $response = new Response();
    $called = false;
    $kernel = $this->app[Kernel::class];
    $kernel->whenRequestLifecycleIsLongerThan(Carbon::now()->addSecond(), function () use (&$called) {
        $called = true;
    });

    Carbon::setTestNow($now = Carbon::now());
    $kernel->handle($request);

    Carbon::setTestNow($now->addSecond()->addMillisecond());
    $kernel->terminate($request, $response);

    expect($called)->toBeTrue();
});

test('it can stay under threshold when specifying duration as date time', function () {
    Route::get('test-route', fn () => 'ok');
    $request = Request::create('http://localhost/test-route');
    $response = new Response();
    $called = false;
    $kernel = $this->app[Kernel::class];
    $kernel->whenRequestLifecycleIsLongerThan(Carbon::now()->addSecond(), function () use (&$called) {
        $called = true;
    });

    Carbon::setTestNow($now = Carbon::now());
    $kernel->handle($request);

    Carbon::setTestNow($now->addSecond());
    $kernel->terminate($request, $response);

    expect($called)->toBeFalse();
});

test('it clears start time after handling request', function () {
    $kernel = $this->app[Kernel::class];
    Route::get('test-route', fn () => 'ok');
    $request = Request::create('http://localhost/test-route');
    $response = new Response();

    Carbon::setTestNow($now = Carbon::now());
    $kernel->handle($request);
    expect($now->eq($kernel->requestStartedAt()))->toBeTrue();

    $kernel->terminate($request, $response);
    expect($kernel->requestStartedAt())->toBeNull();
});

test('it handles calling terminate without handle', function () {
    $this->app[Kernel::class]->terminate(Request::create('http://localhost/test-route'), new Response);

    // this is a placeholder just to show that the above did not throw an exception.
    expect(true)->toBeTrue();
});
