<?php

namespace Venusian\Serve\Tests\Routing;

use Mariner\Http\RedirectResponse;
use Mariner\Http\Request;
use Mariner\Routing\Redirector;
use Mariner\Routing\UrlGenerator;
use Mariner\Session\NullSessionHandler;
use Mariner\Session\Store;
use Mockery;

beforeEach(function () {
    // Built through the constructor: Symfony 8.1 deprecates assigning Request::$headers directly.
    $this->request = Mockery::mock(Request::class)->makePartial();
    $this->request->initialize();
    $this->headers = $this->request->headers;
    $this->request->shouldReceive('isMethod')->andReturn(true)->byDefault();
    $this->request->shouldReceive('method')->andReturn('GET')->byDefault();
    $this->request->shouldReceive('route')->andReturn(true)->byDefault();
    $this->request->shouldReceive('ajax')->andReturn(false)->byDefault();
    $this->request->shouldReceive('expectsJson')->andReturn(false)->byDefault();

    $this->url = Mockery::mock(UrlGenerator::class);
    $this->url->shouldReceive('getRequest')->andReturn($this->request);
    $this->url->shouldReceive('to')->with('bar', [], null)->andReturn('http://foo.com/bar');
    $this->url->shouldReceive('to')->with('bar', [], true)->andReturn('https://foo.com/bar');
    $this->url->shouldReceive('to')->with('login', [], null)->andReturn('http://foo.com/login');
    $this->url->shouldReceive('to')->with('http://foo.com/bar', [], null)->andReturn('http://foo.com/bar');
    $this->url->shouldReceive('to')->with('/', [], null)->andReturn('http://foo.com/');
    $this->url->shouldReceive('to')->with('http://foo.com/bar?signature=secret', [], null)->andReturn('http://foo.com/bar?signature=secret');

    $this->session = new Store('test', new NullSessionHandler);

    $this->redirect = new Redirector($this->url);
    $this->redirect->setSession($this->session);
});

test('basic redirect to', function () {
    $response = $this->redirect->to('bar');

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getTargetUrl())->toBe('http://foo.com/bar');
    expect($response->getStatusCode())->toEqual(302);
    expect($response->getSession())->toEqual($this->session);
});

test('complex redirect to', function () {
    $response = $this->redirect->to('bar', 303, ['X-RateLimit-Limit' => 60, 'X-RateLimit-Remaining' => 59], true);

    expect($response->getTargetUrl())->toBe('https://foo.com/bar');
    expect($response->getStatusCode())->toEqual(303);
    expect($response->headers->get('X-RateLimit-Limit'))->toEqual(60);
    expect($response->headers->get('X-RateLimit-Remaining'))->toEqual(59);
});

test('guest put current url in session', function () {
    $this->url->expects('full')->andReturn('http://foo.com/bar');

    $response = $this->redirect->guest('login');

    expect($response->getTargetUrl())->toBe('http://foo.com/login');
    expect($this->session->get('url.intended'))->toBe('http://foo.com/bar');
});

test('guest put previous url in session', function () {
    $this->request->expects('isMethod')->with('GET')->andReturn(false);
    $this->url->expects('previous')->andReturn('http://foo.com/bar');

    $response = $this->redirect->guest('login');

    expect($response->getTargetUrl())->toBe('http://foo.com/login');
    expect($this->session->get('url.intended'))->toBe('http://foo.com/bar');
});

test('intended redirect to intended url in session', function () {
    $this->session->put('url.intended', 'http://foo.com/bar');

    $response = $this->redirect->intended();

    expect($response->getTargetUrl())->toBe('http://foo.com/bar');
    expect($this->session->get('url.intended'))->toBeNull();
});

test('intended without intended url in session', function () {
    // without fallback url
    $response = $this->redirect->intended();
    expect($response->getTargetUrl())->toBe('http://foo.com/');

    // with a fallback url
    $response = $this->redirect->intended('bar');
    expect($response->getTargetUrl())->toBe('http://foo.com/bar');
});

test('refresh redirect to current url', function () {
    $this->request->expects('path')->andReturn('http://foo.com/bar');
    $response = $this->redirect->refresh();
    expect($response->getTargetUrl())->toBe('http://foo.com/bar');
});

test('back redirect to http referer', function () {
    $this->url->expects('previous')->andReturn('http://foo.com/bar');
    $response = $this->redirect->back();
    expect($response->getTargetUrl())->toBe('http://foo.com/bar');
});

test('away doesnt validate the url', function () {
    $response = $this->redirect->away('bar');
    expect($response->getTargetUrl())->toBe('bar');
});

test('secure redirect to https url', function () {
    $response = $this->redirect->secure('bar');
    expect($response->getTargetUrl())->toBe('https://foo.com/bar');
});

test('action', function () {
    $this->url->expects('action')->with('bar@index', [])->andReturn('http://foo.com/bar');
    $response = $this->redirect->action('bar@index');
    expect($response->getTargetUrl())->toBe('http://foo.com/bar');
});

test('route', function () {
    $this->url->expects('route')->with('home', [])->andReturn('http://foo.com/bar');

    $response = $this->redirect->route('home');
    expect($response->getTargetUrl())->toBe('http://foo.com/bar');
});

test('signed route', function () {
    $this->url->expects('signedRoute')->with('home', [], null)->andReturn('http://foo.com/bar?signature=secret');

    $response = $this->redirect->signedRoute('home');
    expect($response->getTargetUrl())->toBe('http://foo.com/bar?signature=secret');
});

test('temporary signed route', function () {
    $this->url->expects('temporarySignedRoute')->with('home', 10, [])->andReturn('http://foo.com/bar?signature=secret');

    $response = $this->redirect->temporarySignedRoute('home', 10);
    expect($response->getTargetUrl())->toBe('http://foo.com/bar?signature=secret');
});

test('it sets and gets valid intended url', function () {
    $result = $this->redirect->setIntendedUrl('http://foo.com/bar');
    expect($result)->toBeInstanceOf(Redirector::class);

    expect($this->redirect->getIntendedUrl())->toBe('http://foo.com/bar');
});
