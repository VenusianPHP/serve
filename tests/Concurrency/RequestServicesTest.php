<?php

use Mariner\Auth\GenericUser;
use Mariner\Http\Request;
use Mariner\NutsAndBolts\Facades\Auth;
use Mariner\NutsAndBolts\Facades\Request as RequestFacade;
use Mariner\NutsAndBolts\Facades\Session;
use Venusian\Serve\Tests\Fixtures\Interleave;
use Venusian\Serve\Tests\Fixtures\WebApp;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->app['config']->set('session.driver', 'array');
});
afterEach(fn () => WebApp::tearDown($this));

/** Runs $body as a request to $uri in its own fiber scope. */
function asRequest($app, string $uri, Closure $body): Closure
{
    return function () use ($app, $uri, $body) {
        $app->forgetScopedInstances();
        $app->registerInstance('request', Request::create($uri));

        return $body();
    };
}

it('keeps each request fiber in its own session', function () {
    $body = fn (string $who) => function () use ($who) {
        Session::put('who', $who);

        Fiber::suspend();

        return [Session::get('who'), session('who'), app('session.store')->get('who')];
    };

    $results = Interleave::run([
        'a' => asRequest($this->app, '/a', $body('alice')),
        'b' => asRequest($this->app, '/b', $body('bob')),
    ]);

    expect($results)->toBe(['a' => ['alice', 'alice', 'alice'], 'b' => ['bob', 'bob', 'bob']]);
});

it('keeps each request fiber signed in as its own user', function () {
    $body = fn (int $id) => function () use ($id) {
        Auth::guard()->setUser(new GenericUser(['id' => $id, 'name' => "user {$id}"]));

        Fiber::suspend();

        return [Auth::id(), auth()->user()->name, request()->user()?->getAuthIdentifier()];
    };

    $results = Interleave::run([
        'a' => asRequest($this->app, '/a', $body(1)),
        'b' => asRequest($this->app, '/b', $body(2)),
    ]);

    expect($results)->toBe(['a' => [1, 'user 1', 1], 'b' => [2, 'user 2', 2]]);
});

it('builds URLs and redirects from each request fiber\'s own request', function () {
    $body = fn () => function () {
        url()->defaults(['locale' => request()->segment(1)]);

        Fiber::suspend();

        return [url()->current(), redirect()->back()->getTargetUrl(), route('home')];
    };

    $results = Interleave::run([
        'a' => asRequest($this->app, 'http://alpha.test/en/page', $body()),
        'b' => asRequest($this->app, 'http://beta.test/fr/page', $body()),
    ]);

    expect($results['a'][0])->toBe('http://alpha.test/en/page')
        ->and($results['b'][0])->toBe('http://beta.test/fr/page')
        ->and($results['a'][2])->toBe('http://alpha.test')
        ->and($results['b'][2])->toBe('http://beta.test')
        ->and($this->app['url']->getDefaultParameters())->not->toHaveKey('locale');   // main scope untouched
});

it('resolves the Request facade per request fiber', function () {
    $body = fn () => function () {
        Fiber::suspend();

        return RequestFacade::path();
    };

    $results = Interleave::run([
        'a' => asRequest($this->app, '/alpha', $body()),
        'b' => asRequest($this->app, '/beta', $body()),
    ]);

    expect($results)->toBe(['a' => 'alpha', 'b' => 'beta']);
});

it('sends each request fiber only the cookies it queued', function () {
    $body = fn (string $flavour) => function () use ($flavour) {
        cookie()->queue(cookie('flavour', $flavour));

        Fiber::suspend();

        return array_map(fn ($cookie) => $cookie->getValue(), cookie()->getQueuedCookies());
    };

    $results = Interleave::run([
        'a' => asRequest($this->app, '/a', $body('chocolate')),
        'b' => asRequest($this->app, '/b', $body('oatmeal')),
    ]);

    expect($results)->toBe(['a' => ['chocolate'], 'b' => ['oatmeal']])
        ->and(cookie()->getQueuedCookies())->toBe([]);   // nothing leaked into the main scope
});

it('sends each request fiber\'s notifications in the locale it chose', function () {
    $body = fn (string $locale) => function () use ($locale) {
        $manager = Mariner\NutsAndBolts\Facades\Notification::locale($locale);

        Fiber::suspend();

        $sender = (fn () => $this->resolveNotificationSender())->call($manager);

        return (fn () => $this->locale)->call($sender);
    };

    $results = Interleave::run([
        'a' => asRequest($this->app, '/a', $body('es')),
        'b' => asRequest($this->app, '/b', $body('fr')),
    ]);

    $shared = $this->app->make(Mariner\Notifications\ChannelManager::class);

    expect($results)->toBe(['a' => 'es', 'b' => 'fr'])
        ->and((fn () => $this->locale)->call($shared))->toBeNull();
});

it('gives each request fiber its own Vite nonce and preloads, under one Vite configuration', function () {
    Mariner\NutsAndBolts\Facades\Vite::useBuildDirectory('assets');   // at boot

    $body = fn (string $who) => function () use ($who) {
        $vite = app(Mariner\View\Vite::class);
        $vite->useCspNonce("nonce-{$who}");
        (fn () => $this->viteState()->preloadedAssets["/{$who}.js"] = [])->call($vite);

        Fiber::suspend();

        return [$vite->cspNonce(), array_keys($vite->preloadedAssets()), (fn () => $this->buildDirectory)->call($vite)];
    };

    $results = Interleave::run([
        'a' => asRequest($this->app, '/a', $body('alice')),
        'b' => asRequest($this->app, '/b', $body('bob')),
    ]);

    expect($results)->toBe([
        'a' => ['nonce-alice', ['/alice.js'], 'assets'],
        'b' => ['nonce-bob', ['/bob.js'], 'assets'],
    ])->and(app(Mariner\View\Vite::class)->cspNonce())->toBeNull();
});
