<?php

use Mariner\Http\Request;
use Mariner\Routing\RouteAction;
use Mariner\Routing\RouteCache;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Contracts\Console\Kernel;

/** Runs a Computer command on the app and returns [exit code, output]. */
function computer($app, string $command): array
{
    $output = new BufferedOutput;
    $code = $app->make(Kernel::class)->handle(new ArrayInput(['command' => $command]), $output);

    return [$code, $output->fetch()];
}

/** name => [methods, uri, action uses (closures as "Closure"), middleware] for every route. */
function routeTable($app): array
{
    $table = [];
    foreach ($app['router']->getRoutes() as $route) {
        $uses = $route->getAction('uses');
        $closure = $uses instanceof Closure || (is_string($uses) && RouteAction::containsSerializedClosure(['uses' => $uses]));
        $table[$route->getName()] = [
            $route->methods(), $route->uri(),
            $closure ? 'Closure' : $uses, $route->gatherMiddleware(), $route->wheres,
        ];
    }
    ksort($table);

    return $table;
}

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->cache = $this->app->make(RouteCache::class);
    $this->cache->clear();
});

afterEach(function () {
    $this->cache->clear();
    WebApp::tearDown($this);
});

it('caches the routes and loads an identical collection from the cache', function () {
    $fromFiles = routeTable($this->app);

    [$code, $output] = computer($this->app, 'route:cache');

    expect($code)->toBe(0)
        ->and($output)->toContain('Routes cached successfully.')
        ->and($this->cache->isCached())->toBeTrue();

    WebApp::tearDown($this);
    $this->app = WebApp::boot();

    expect(routeTable($this->app))->toBe($fromFiles)
        ->and(route('users.show', ['id' => 7]))->toBe('http://localhost/users/7');
});

it('keeps closure routes runnable from the cache, as Laravel does', function () {
    computer($this->app, 'route:cache');

    WebApp::tearDown($this);
    $this->app = WebApp::boot();

    $route = $this->app['router']->getRoutes()->getByName('home');

    expect($route->getAction('uses'))->toBeString()   // serialized closure, unwrapped when run
        ->and($route->bind(Request::create('/'))->run())->toBe('home');
});

it('loads from the route files again once the cache is cleared', function () {
    computer($this->app, 'route:cache');

    [$code, $output] = computer($this->app, 'route:clear');

    expect($code)->toBe(0)
        ->and($output)->toContain('Route cache cleared successfully.')
        ->and($this->cache->isCached())->toBeFalse();

    WebApp::tearDown($this);
    $this->app = WebApp::boot();

    expect($this->app['router']->getRoutes()->getByName('home')->getAction('uses'))->toBeInstanceOf(Closure::class);
});
