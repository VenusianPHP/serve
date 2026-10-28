<?php

use Mariner\Contracts\Routing\UrlRoutable;
use Mariner\Http\RedirectResponse;
use Mariner\Http\Request;
use Mariner\Routing\Uri;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\NutsAndBolts\Str;

beforeEach(fn () => $this->app = WebApp::boot());
afterEach(fn () => WebApp::tearDown($this));

it('turns a Uri returned from a controller into a redirect', function () {
    $request = Request::create('http://localhost/uri-redirect');
    $this->app->registerInstance('request', $request);

    $response = $this->app['router']->dispatch($request);

    expect($response)->toBeInstanceOf(RedirectResponse::class)
        ->and($response->getStatusCode())->toBe(302)
        ->and($response->getTargetUrl())->toBe('http://localhost/uri-redirect?page=2');
});

it('hands user code the web Uri everywhere, and keeps it through chaining', function () {
    $this->app->registerInstance('request', Request::create('http://localhost/a?b=c'));

    expect(request()->uri())->toBeInstanceOf(Uri::class)
        ->and(request()->uri()->withQuery(['d' => 'e'])->withPath('/f'))->toBeInstanceOf(Uri::class)
        ->and(Str::of('http://localhost/x')->toUri())->toBeInstanceOf(Uri::class)
        ->and(Uri::route('home'))->toBeInstanceOf(Uri::class)
        ->and((string) Uri::route('users.show', ['id' => 5]))->toBe('http://localhost/users/5');
});

it('writes routable models into a query as their route keys', function () {
    $model = new class implements UrlRoutable {
        public function getRouteKey() { return 'slug-7'; }
        public function getRouteKeyName() { return 'slug'; }
        public function resolveRouteBinding($value, $field = null) { return null; }
        public function resolveChildRouteBinding($childType, $value, $field) { return null; }
    };

    expect((string) Uri::of('http://localhost/posts')->withQuery(['post' => $model]))
        ->toBe('http://localhost/posts?post=slug-7');
});
