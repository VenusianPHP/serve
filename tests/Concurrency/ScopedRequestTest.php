<?php

use Mariner\Http\Request;
use Venusian\Serve\Tests\Fixtures\Interleave;
use Venusian\Serve\Tests\Fixtures\WebApp;

beforeEach(fn () => $this->app = WebApp::boot());
afterEach(fn () => WebApp::tearDown($this));

it('gives each request fiber its own request, through the container and the helper', function () {
    $body = fn (string $path) => function () use ($path) {
        $this->app->forgetScopedInstances();
        $this->app->registerInstance('request', Request::create($path));

        Fiber::suspend();

        return [app('request')->path(), request()->path()];
    };

    $results = Interleave::run(['a' => $body('/alpha'), 'b' => $body('/beta')]);

    expect($results)->toBe(['a' => ['alpha', 'alpha'], 'b' => ['beta', 'beta']])
        ->and(app('request')->path())->toBe('/');   // the main context keeps the console request
});
