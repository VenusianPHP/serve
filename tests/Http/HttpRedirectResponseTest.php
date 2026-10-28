<?php

namespace Venusian\Serve\Tests\Http;

use BadMethodCallException;
use Mariner\Contracts\NutsAndBolts\MessageProvider;
use Mariner\Http\RedirectResponse;
use Mariner\Http\Request;
use Mariner\Session\NullSessionHandler;
use Mariner\Session\Store;
use Mariner\NutsAndBolts\MessageBag;
use Mariner\NutsAndBolts\ViewErrorBag;
use Mockery;
use Symfony\Component\HttpFoundation\Cookie;

test('to response returns the same instance', function () {
    $response = new RedirectResponse('foo.bar');

    expect($response->toResponse(Request::create('/')))->toBe($response);
});

test('header on redirect', function () {
    $response = new RedirectResponse('foo.bar');
    expect($response->headers->get('foo'))->toBeNull();
    $response->header('foo', 'bar');
    expect($response->headers->get('foo'))->toBe('bar');
    $response->header('foo', 'baz', false);
    expect($response->headers->get('foo'))->toBe('bar');
    $response->header('foo', 'baz');
    expect($response->headers->get('foo'))->toBe('baz');
});

test('with on redirect', function () {
    $response = new RedirectResponse('foo.bar');
    $response->setRequest(Request::create('/', 'GET', ['name' => 'Taylor', 'age' => 26]));
    $session = Mockery::mock(Store::class);
    $response->setSession($session);
    $session->expects('flash')->times(2);
    $response->with(['name', 'age']);
});

test('with cookie on redirect', function () {
    $response = new RedirectResponse('foo.bar');
    expect($response->headers->getCookies())->toHaveCount(0);
    expect($response->withCookie(new Cookie('foo', 'bar')))->toEqual($response);
    $cookies = $response->headers->getCookies();
    expect($cookies)->toHaveCount(1);
    expect($cookies[0]->getName())->toBe('foo');
    expect($cookies[0]->getValue())->toBe('bar');
});

test('fragment identifier on redirect', function () {
    $response = new RedirectResponse('foo.bar');

    $response->withFragment('foo');
    expect(parse_url($response->getTargetUrl(), PHP_URL_FRAGMENT))->toBe('foo');

    $response->withFragment('#bar');
    expect(parse_url($response->getTargetUrl(), PHP_URL_FRAGMENT))->toBe('bar');

    $response->withoutFragment();
    expect(parse_url($response->getTargetUrl(), PHP_URL_FRAGMENT))->toBeNull();
});

test('input on redirect', function () {
    $response = new RedirectResponse('foo.bar');
    $response->setRequest(Request::create('/', 'GET', ['name' => 'Taylor', 'age' => 26]));
    $session = new Store('test', new NullSessionHandler);
    $response->setSession($session);
    $response->withInput();

    expect($session->getOldInput())->toBe(['name' => 'Taylor', 'age' => 26]);
    expect($session->get('_flash.new', []))->toContain('_old_input');
});

test('with cookies', function () {
    $response = new RedirectResponse('foo.bar');
    $response->setRequest(Request::create('/', 'GET', ['name' => 'Taylor', 'age' => 26]));
    $response->withCookies([
        new Cookie('name', 'milwad'),
    ]);

    expect($response->headers->getCookies()[0]->getName())->toBe('name');
    expect($response->headers->getCookies()[0]->getValue())->toBe('milwad');
});

test('only input on redirect', function () {
    $response = new RedirectResponse('foo.bar');
    $response->setRequest(Request::create('/', 'GET', ['name' => 'Taylor', 'age' => 26]));
    $session = new Store('test', new NullSessionHandler);
    $response->setSession($session);
    $response->onlyInput('name');

    expect($session->getOldInput())->toBe(['name' => 'Taylor']);
    expect($session->get('_flash.new', []))->toContain('_old_input');
});

test('except input on redirect', function () {
    $response = new RedirectResponse('foo.bar');
    $response->setRequest(Request::create('/', 'GET', ['name' => 'Taylor', 'age' => 26]));
    $session = new Store('test', new NullSessionHandler);
    $response->setSession($session);
    $response->exceptInput('age');

    expect($session->getOldInput())->toBe(['name' => 'Taylor']);
    expect($session->get('_flash.new', []))->toContain('_old_input');
});

test('flashing errors on redirect', function () {
    $response = new RedirectResponse('foo.bar');
    $response->setRequest(Request::create('/', 'GET', ['name' => 'Taylor', 'age' => 26]));
    $session = new Store('test', new NullSessionHandler);
    $response->setSession($session);
    $provider = Mockery::mock(MessageProvider::class);
    $provider->expects('getMessageBag')->andReturn(new MessageBag(['name' => ['required']]));
    $response->withErrors($provider);

    expect($session->get('_flash.new', []))->toContain('errors');
    $errors = $session->get('errors');
    expect($errors)->toBeInstanceOf(ViewErrorBag::class);
    expect($errors->getBag('default')->get('name'))->toBe(['required']);
});

test('can enforce same origin when same origin', function () {
    $response = new RedirectResponse('https://example.com/foo/bar');
    $response->setRequest(Request::create('https://example.com/baz/buzz'));
    $response->enforceSameOrigin('fallback');

    expect($response->getTargetUrl())->toBe('https://example.com/foo/bar');
});

test('can enforce same origin when same origin and custom port', function () {
    $response = new RedirectResponse('https://example.com:1/foo/bar');
    $response->setRequest(Request::create('https://example.com:1/baz/buzz'));
    $response->enforceSameOrigin('fallback');

    expect($response->getTargetUrl())->toBe('https://example.com:1/foo/bar');
});

test('can enforce same origin when not same scheme', function () {
    $response = new RedirectResponse('https://example.com/foo/bar');
    $response->setRequest(Request::create('http://example.com/baz/buzz'));
    $response->enforceSameOrigin('fallback');

    expect($response->getTargetUrl())->toBe('fallback');
});

test('can enforce same origin when not same hostname', function () {
    $response = new RedirectResponse('https://example.com/foo/bar');
    $response->setRequest(Request::create('https://example2.com/baz/buzz'));
    $response->enforceSameOrigin('fallback');

    expect($response->getTargetUrl())->toBe('fallback');
});

test('can enforce same origin when not same port', function () {
    $response = new RedirectResponse('https://example.com:1/foo/bar');
    $response->setRequest(Request::create('https://example.com:2/baz/buzz'));
    $response->enforceSameOrigin('fallback');

    expect($response->getTargetUrl())->toBe('fallback');
});

test('can enforce same origin when not same scheme and scheme validation is disabled', function () {
    $response = new RedirectResponse('https://example.com/foo/bar');
    $response->setRequest(Request::create('http://example.com/baz/buzz'));
    $response->enforceSameOrigin('fallback', validateScheme: false);

    expect($response->getTargetUrl())->toBe('https://example.com/foo/bar');
});

test('can enforce same origin when not same port and port validation is disabled', function () {
    $response = new RedirectResponse('https://example.com:1/foo/bar');
    $response->setRequest(Request::create('https://example.com:2/baz/buzz'));
    $response->enforceSameOrigin('fallback', validatePort: false);

    expect($response->getTargetUrl())->toBe('https://example.com:1/foo/bar');
});

test('setters getters on request', function () {
    $response = new RedirectResponse('foo.bar');
    expect($response->getRequest())->toBeNull();
    expect($response->getSession())->toBeNull();

    $request = Request::create('/', 'GET');
    $session = new Store('test', new NullSessionHandler);
    $response->setRequest($request);
    $response->setSession($session);
    expect($response->getRequest())->toBe($request);
    expect($response->getSession())->toBe($session);
});

test('redirect with errors array converts to message bag', function () {
    $response = new RedirectResponse('foo.bar');
    $response->setRequest(Request::create('/', 'GET', ['name' => 'Taylor', 'age' => 26]));
    $session = new Store('test', new NullSessionHandler);
    $response->setSession($session);
    $provider = ['foo' => 'bar'];
    $response->withErrors($provider);

    expect($session->get('_flash.new', []))->toContain('errors');
    $errors = $session->get('errors');
    expect($errors)->toBeInstanceOf(ViewErrorBag::class);
    expect($errors->getBag('default')->get('foo'))->toBe(['bar']);
});

test('magic call', function () {
    $response = new RedirectResponse('foo.bar');
    $response->setRequest(Request::create('/', 'GET', ['name' => 'Taylor', 'age' => 26]));
    $session = new Store('test', new NullSessionHandler);
    $response->setSession($session);
    $response->withFoo('bar');

    expect($session->get('foo'))->toBe('bar');
    expect($session->get('_flash.new', []))->toContain('foo');
});

test('magic call exception', function () {

    $response = new RedirectResponse('foo.bar');
    $response->doesNotExist('bar');
})->throws(BadMethodCallException::class, 'Call to undefined method Mariner\Http\RedirectResponse::doesNotExist()');
