<?php

use Mariner\Http\Request;
use Venusian\Serve\Tests\Fixtures\Interleave;
use Venusian\Serve\Tests\Fixtures\WebApp;

beforeEach(fn () => $this->app = WebApp::boot());
afterEach(fn () => WebApp::tearDown($this));

/** A request body for Interleave: a fresh scope, its own request, dispatched through the router. */
function dispatchInFiber($app, string $uri): Closure
{
    return function () use ($app, $uri) {
        $app->forgetScopedInstances();
        $request = Request::create($uri);
        $app->registerInstance('request', $request);

        return $app['router']->dispatch($request)->getContent();
    };
}

it('keeps route parameters, the current route and the controller per request fiber', function () {
    $results = Interleave::run([
        'one' => dispatchInFiber($this->app, '/fibers/1'),
        'two' => dispatchInFiber($this->app, '/fibers/2'),
    ]);

    $one = json_decode($results['one'], true);
    $two = json_decode($results['two'], true);

    expect([$one['argument'], $one['parameter'], $one['path'], $one['controller_saw']])->toBe(['1', '1', 'fibers/1', '1'])
        ->and([$two['argument'], $two['parameter'], $two['path'], $two['controller_saw']])->toBe(['2', '2', 'fibers/2', '2'])
        ->and($one['controller'])->not->toBe($two['controller']);
});

it('leaves the shared route unbound after dispatching', function () {
    Interleave::run(['one' => dispatchInFiber($this->app, '/fibers/1')]);

    $shared = $this->app['router']->getRoutes()->getByName('fibers.show');

    expect($shared->hasParameters())->toBeFalse()
        ->and($shared->controller)->toBeNull();
});
