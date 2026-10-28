<?php

namespace Venusian\Serve\Tests\Http;

use BadMethodCallException;
use Voyager\Contracts\NutsAndBolts\Arrayable;
use Voyager\Contracts\NutsAndBolts\Jsonable;
use Mariner\Contracts\NutsAndBolts\MessageProvider;
use Mariner\Contracts\NutsAndBolts\Renderable;
use Mariner\Http\RedirectResponse;
use Mariner\Http\Request;
use Mariner\Http\Response;
use Mariner\Session\NullSessionHandler;
use Mariner\Session\Store;
use Mariner\NutsAndBolts\MessageBag;
use Mariner\NutsAndBolts\ViewErrorBag;
use JsonSerializable;
use Mockery;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\HeaderBag;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

test('json responses are converted and headers are set', function () {
    $response = new Response(new ArrayableStub);
    expect($response->getContent())->toBe('{"foo":"bar"}');
    expect($response->headers->get('Content-Type'))->toBe('application/json');

    $response = new Response(new JsonableStub);
    expect($response->getContent())->toBe('foo');
    expect($response->headers->get('Content-Type'))->toBe('application/json');

    $response = new Response(new ArrayableAndJsonableStub);
    expect($response->getContent())->toBe('{"foo":"bar"}');
    expect($response->headers->get('Content-Type'))->toBe('application/json');

    $response = new Response;
    $response->setContent(['foo' => 'bar']);
    expect($response->getContent())->toBe('{"foo":"bar"}');
    expect($response->headers->get('Content-Type'))->toBe('application/json');

    $response = new Response(new JsonSerializableStub);
    expect($response->getContent())->toBe('{"foo":"bar"}');
    expect($response->headers->get('Content-Type'))->toBe('application/json');

    $response = new Response(new ArrayableStub);
    expect($response->getContent())->toBe('{"foo":"bar"}');
    expect($response->headers->get('Content-Type'))->toBe('application/json');

    $response->setContent('{"foo": "bar"}');
    expect($response->getContent())->toBe('{"foo": "bar"}');
    expect($response->headers->get('Content-Type'))->toBe('application/json');
});

test('renderables are rendered', function () {
    $renderable = new class implements Renderable
    {
        public function render()
        {
            return 'foo';
        }
    };
    $response = new Response($renderable);
    expect($response->getContent())->toBe('foo');
});

test('header', function () {
    $response = new Response;
    expect($response->headers->get('foo'))->toBeNull();
    $response->header('foo', 'bar');
    expect($response->headers->get('foo'))->toBe('bar');
    $response->header('foo', 'baz', false);
    expect($response->headers->get('foo'))->toBe('bar');
    $response->header('foo', 'baz');
    expect($response->headers->get('foo'))->toBe('baz');
});

test('with cookie', function () {
    $response = new Response;
    expect($response->headers->getCookies())->toHaveCount(0);
    expect($response->withCookie(new Cookie('foo', 'bar')))->toEqual($response);
    $cookies = $response->headers->getCookies();
    expect($cookies)->toHaveCount(1);
    expect($cookies[0]->getName())->toBe('foo');
    expect($cookies[0]->getValue())->toBe('bar');
});

test('with cookies', function () {
    $response = new Response;
    expect($response->headers->getCookies())->toHaveCount(0);
    expect($response->withCookies([
        new Cookie('foo', 'bar'),
        new Cookie('baz', 'qux'),
    ]))->toEqual($response);
    $cookies = $response->headers->getCookies();
    expect($cookies)->toHaveCount(2);
    expect($cookies[0]->getName())->toBe('foo');
    expect($cookies[0]->getValue())->toBe('bar');
    expect($cookies[1]->getName())->toBe('baz');
    expect($cookies[1]->getValue())->toBe('qux');
});

test('without cookie', function () {
    $response = new Response;
    expect($response->headers->getCookies())->toHaveCount(0);
    expect($response->withoutCookie(new Cookie('foo', 'bar')))->toEqual($response);
    $cookies = $response->headers->getCookies();
    expect($cookies)->toHaveCount(1);
    expect($cookies[0]->getName())->toBe('foo');
});

test('without cookies', function () {
    $response = new Response;
    expect($response->headers->getCookies())->toHaveCount(0);
    expect($response->withoutCookies([
        new Cookie('foo', 'bar'),
        new Cookie('baz', 'qux'),
    ]))->toEqual($response);
    $cookies = $response->headers->getCookies();
    expect($cookies)->toHaveCount(2);
    expect($cookies[0]->getName())->toBe('foo');
    expect($cookies[1]->getName())->toBe('baz');
});

test('response cookies inherit request secure state', function () {
    $cookie = Cookie::create('foo', 'bar');

    $response = new Response('foo');
    $response->headers->setCookie($cookie);

    $request = Request::create('/', 'GET');
    $response->prepare($request);

    expect($cookie->isSecure())->toBeFalse();

    $request = Request::create('https://localhost/', 'GET');
    $response->prepare($request);

    expect($cookie->isSecure())->toBeTrue();
});

test('get original content', function () {
    $arr = ['foo' => 'bar'];
    $response = new Response;
    $response->setContent($arr);
    expect($response->getOriginalContent())->toBe($arr);
});

test('get original content retrieves the first original content', function () {
    $previousResponse = new Response(['foo' => 'bar']);
    $response = new Response($previousResponse);

    expect($response->getOriginalContent())->toBe(['foo' => 'bar']);
});

test('set and retrieve status code', function () {
    $response = new Response('foo');
    $response->setStatusCode(404);
    expect($response->getStatusCode())->toBe(404);
});

test('set status code and retrieve status text', function () {
    $response = new Response('foo');
    $response->setStatusCode(404);
    expect($response->statusText())->toBe('Not Found');
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

test('with headers', function () {
    $response = new Response(null, 200, ['foo' => 'bar']);
    expect($response->headers->get('foo'))->toBe('bar');

    $response->withHeaders(['foo' => 'BAR', 'bar' => 'baz']);
    expect($response->headers->get('foo'))->toBe('BAR');
    expect($response->headers->get('bar'))->toBe('baz');

    $responseMessageBag = new ResponseHeaderBag(['bar' => 'BAZ', 'titi' => 'toto']);
    $response->withHeaders($responseMessageBag);
    expect($response->headers->get('bar'))->toBe('BAZ');
    expect($response->headers->get('titi'))->toBe('toto');

    $headerBag = new HeaderBag(['bar' => 'BAAA', 'titi' => 'TATA']);
    $response->withHeaders($headerBag);
    expect($response->headers->get('bar'))->toBe('BAAA');
    expect($response->headers->get('titi'))->toBe('TATA');
});

test('without header', function () {
    $response = new Response(null, 200, ['foo' => 'bar', 'baz' => 'qux', 'zal' => 'ter']);
    expect($response->headers->get('foo'))->toBe('bar');
    expect($response->headers->get('baz'))->toBe('qux');
    expect($response->headers->get('zal'))->toBe('ter');

    // Test removing single header
    $result = $response->withoutHeader('foo');
    expect($result)->toBe($response);
    expect($response->headers->get('foo'))->toBeNull();
    expect($response->headers->get('baz'))->toBe('qux');
    expect($response->headers->get('zal'))->toBe('ter');

    // Test removing multiple headers at once
    $response->withoutHeader(['baz', 'zal']);
    expect($response->headers->get('baz'))->toBeNull();
    expect($response->headers->get('zal'))->toBeNull();
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

class ArrayableStub implements Arrayable
{
    public function toArray()
    {
        return ['foo' => 'bar'];
    }
}

class ArrayableAndJsonableStub implements Arrayable, Jsonable
{
    public function toJson(int $options = 0): string
    {
        return '{"foo":"bar"}';
    }

    public function toArray()
    {
        return [];
    }
}

class JsonableStub implements Jsonable
{
    public function toJson(int $options = 0): string
    {
        return 'foo';
    }
}

class JsonSerializableStub implements JsonSerializable
{
    public function jsonSerialize(): array
    {
        return ['foo' => 'bar'];
    }
}
