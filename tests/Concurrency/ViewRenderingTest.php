<?php

use Mariner\Http\Request;
use Mariner\NutsAndBolts\Facades\View;
use Mariner\Routing\RouteState;
use Venusian\Serve\Tests\Fixtures\WebApp;

beforeEach(function () {
    $this->app = WebApp::boot();

    View::share('greeting', 'hello');   // at boot: every request's views see it
});
afterEach(fn () => WebApp::tearDown($this));

/** A request handled as an event-loop task, in its own scope, as the router marks it during dispatch. */
function handleOnLoop($app, string $uri, Closure $body)
{
    return $app['event-loop']->async(function () use ($app, $uri, $body) {
        $app->forgetScopedInstances();
        $request = Request::create($uri);
        $app->registerInstance('request', $request);
        $app->make(RouteState::class)->request = $request;

        return $body();
    });
}

it('renders each request\'s sections, stacks, output and shared data while both pause mid-section', function () {
    $body = fn (string $who) => function () use ($who) {
        View::share('mood', "{$who}-mood");   // while handling: this request only

        return preg_replace('/\s+/', ' ', trim(view('page', ['who' => $who])->render()));
    };

    $loop = $this->app['event-loop'];
    $a = handleOnLoop($this->app, '/a', $body('alice'));
    $b = handleOnLoop($this->app, '/b', $body('bob'));

    expect($loop->await($a))->toBe('<title>alice</title> <main>before alice after alice hello alice-mood </main> <script>alice</script>')
        ->and($loop->await($b))->toBe('<title>bob</title> <main>before bob after bob hello bob-mood </main> <script>bob</script>')
        ->and(View::shared('mood'))->toBeNull()
        ->and(View::shared('greeting'))->toBe('hello');
});
