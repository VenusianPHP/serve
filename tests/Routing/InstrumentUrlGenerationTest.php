<?php

use Mariner\Auth\User;
use Venusian\Serve\Tests\Fixtures\Article;
use Venusian\Serve\Tests\Fixtures\WebApp;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->router = $this->app['router'];
});

afterEach(fn () => WebApp::tearDown($this));

it('fills a route parameter from a plain Instrument model\'s route key', function () {
    $this->router->get('members/{user}', fn () => '')->name('members.show');
    $this->router->getRoutes()->refreshNameLookups();

    $user = (new User)->forceFill(['id' => 7]);

    expect($this->app['url']->route('members.show', $user, false))->toBe('/members/7')
        ->and($this->app['url']->route('members.show', ['user' => $user], false))->toBe('/members/7')
        ->and($this->app['url']->to('users', [$user]))->toEndWith('/users/7');
});

it('uses the route\'s binding field for a plain Instrument model', function () {
    $this->router->get('articles/{article:slug}', fn () => '')->name('articles.show');
    $this->router->getRoutes()->refreshNameLookups();

    $article = (new Article)->forceFill(['id' => 3, 'slug' => 'engines']);

    expect($this->app['url']->route('articles.show', $article, false))->toBe('/articles/engines');
});

it('puts a plain Instrument model\'s route key in a query string', function () {
    $user = (new User)->forceFill(['id' => 9]);

    expect((string) Mariner\Routing\Uri::of('https://example.test/search')->withQuery(['owner' => $user]))
        ->toBe('https://example.test/search?owner=9');
});
