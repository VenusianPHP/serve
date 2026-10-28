<?php

use Mariner\Http\Request;
use Mariner\Routing\Middleware\ThrottleRequests;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Cache\AsyncRateLimiter;
use Voyager\Cache\RateLimiter;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->dir = sys_get_temp_dir().'/mariner-throttle-'.bin2hex(random_bytes(4));

    // A file store: its async operations run on worker processes, on the real clock.
    $this->app['config']->set('cache.stores.throttle', ['driver' => 'file', 'path' => $this->dir]);
    $this->app['config']->set('cache.limiter', 'throttle');

    // Counts the limiter calls that went through the async variant.
    $this->asyncCalls = 0;
    $limiter = new class($this->app['cache']->store('throttle'), $this) extends RateLimiter {
        public function __construct($cache, private $test)
        {
            parent::__construct($cache);
        }

        public function async(): AsyncRateLimiter
        {
            $this->test->asyncCalls++;

            return parent::async();
        }
    };
    $this->app->registerInstance(RateLimiter::class, $limiter);

    $this->app['router']->get('limited', fn () => 'ok')->middleware(ThrottleRequests::class.':2,1');
});

afterEach(function () {
    $this->app['files']->deleteDirectory($this->dir);
    WebApp::tearDown($this);
});

/** Dispatches a GET from one client and returns the response. */
function throttled($app)
{
    $request = Request::create('/limited', 'GET', server: ['REMOTE_ADDR' => '10.0.0.1']);
    $app->registerInstance('request', $request);

    return $app['router']->dispatch($request);
}

dataset('modes', [false, true]);

it('throttles the same way in place and through the async limiter', function (bool $async) {
    $this->app['config']->set('routing.throttle_async', $async);

    $first = throttled($this->app);
    $second = throttled($this->app);
    $third = throttled($this->app);

    expect($first->getStatusCode())->toBe(200)
        ->and($first->headers->get('X-RateLimit-Remaining'))->toBe('1')
        ->and($second->headers->get('X-RateLimit-Remaining'))->toBe('0')
        ->and($third->getStatusCode())->toBe(429)
        ->and($third->headers->get('Retry-After'))->not->toBeNull()
        ->and($this->asyncCalls > 0)->toBe($async);
})->with('modes');

it('lets two throttled requests in loop tasks overlap their limiter calls', function () {
    $this->app['config']->set('routing.throttle_async', true);
    $loop = $this->app['event-loop'];
    $events = [];

    $request = function (string $name) use ($loop, &$events) {
        return $loop->async(function () use ($name, &$events) {
            $events[] = "{$name}:start";
            $status = throttled($this->app)->getStatusCode();
            $events[] = "{$name}:done";

            return $status;
        });
    };

    $a = $request('a');
    $b = $request('b');

    expect([$loop->await($a), $loop->await($b)])->toBe([200, 200])
        ->and(array_search('b:start', $events))->toBeLessThan(array_search('a:done', $events));
});

it('fails loudly when the limiter\'s cache has no async operations', function () {
    $this->app['config']->set('routing.throttle_async', true);
    $this->app->registerInstance(RateLimiter::class, new RateLimiter(new Voyager\Cache\Repository(new Voyager\Cache\ArrayStore)));

    expect(throttled($this->app)->getStatusCode())->toBe(500);
});
