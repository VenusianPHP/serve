<?php

namespace Venusian\Serve\Tests\Http\Middleware;

use Mariner\Http\Middleware\PrefersJsonResponses;
use Mariner\Http\Request;
use Symfony\Component\HttpFoundation\Response;

test('it rewrites missing accept header', function () {
    $request = Request::create('/', 'GET');
    $request->headers->remove('Accept');

    runMiddleware($request);

    expect($request->headers->get('Accept'))->toBe('application/json');
    expect($request->headers->has('X-Original-Accept'))->toBeFalse();
    expect($request->wantsJson())->toBeTrue();
    expect($request->expectsJson())->toBeTrue();
});

test('it rewrites empty accept header', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '']);

    runMiddleware($request);

    expect($request->headers->get('Accept'))->toBe('application/json');
});

test('it rewrites star slash star accept header', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '*/*']);

    runMiddleware($request);

    expect($request->headers->get('Accept'))->toBe('application/json');
    expect($request->headers->get('X-Original-Accept'))->toBe('*/*');
    expect($request->wantsJson())->toBeTrue();
});

test('it does not rewrite bare star accept header', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '*']);

    runMiddleware($request);

    expect($request->headers->get('Accept'))->toBe('*');
    expect($request->headers->has('X-Original-Accept'))->toBeFalse();
});

test('it rewrites application wildcard accept header', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/*']);

    runMiddleware($request);

    expect($request->headers->get('Accept'))->toBe('application/json');
});

test('it rewrites broad accept header with quality parameter', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '*/*;q=0.8']);

    runMiddleware($request);

    expect($request->headers->get('Accept'))->toBe('application/json');
});

test('it rewrites multiple broad markers', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/*, */*;q=0.5']);

    runMiddleware($request);

    expect($request->headers->get('Accept'))->toBe('application/json');
    expect($request->headers->get('X-Original-Accept'))->toBe('application/*, */*;q=0.5');
});

test('it invokes next and returns its response', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '*/*']);

    $called = false;
    $expected = new Response('ok');

    $result = (new PrefersJsonResponses)->handle($request, function ($passed) use ($request, &$called, $expected) {
        $called = true;
        expect($passed)->toBe($request);

        return $expected;
    });

    expect($called)->toBeTrue();
    expect($result)->toBe($expected);
});

test('it leaves explicit html accept header', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html']);

    runMiddleware($request);

    expect($request->headers->get('Accept'))->toBe('text/html');
    expect($request->wantsJson())->toBeFalse();
});

test('it leaves explicit xml accept header', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/xml']);

    runMiddleware($request);

    expect($request->headers->get('Accept'))->toBe('application/xml');
});

test('it leaves explicit plain text accept header', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/plain']);

    runMiddleware($request);

    expect($request->headers->get('Accept'))->toBe('text/plain');
});

test('it leaves multi value explicit accept list', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html, text/plain']);

    runMiddleware($request);

    expect($request->headers->get('Accept'))->toBe('text/html, text/plain');
    expect($request->wantsJson())->toBeFalse();
});

test('it leaves mixed broad and explicit accept list', function () {
    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_ACCEPT' => 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
    ]);

    runMiddleware($request);

    expect($request->headers->get('Accept'))->toBe('text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8');
    expect($request->headers->has('X-Original-Accept'))->toBeFalse();
});

test('it leaves wildcard first mixed accept list', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '*/*, text/html']);

    runMiddleware($request);

    expect($request->headers->get('Accept'))->toBe('*/*, text/html');
});

test('it leaves explicit json accept header', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);

    runMiddleware($request);

    expect($request->headers->get('Accept'))->toBe('application/json');
    expect($request->wantsJson())->toBeTrue();
});

test('it does not mutate body method uri query or other headers', function () {
    $request = Request::create(
        '/resource?page=2',
        'POST',
        ['foo' => 'bar'],
        [],
        [],
        [
            'HTTP_ACCEPT' => '*/*',
            'HTTP_X_CUSTOM' => 'value',
            'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
        ],
        'raw-body'
    );

    $originalMethod = $request->getMethod();
    $originalPath = $request->getPathInfo();
    $originalQuery = $request->query->all();
    $originalBody = $request->getContent();
    $originalCustomHeader = $request->headers->get('X-Custom');

    runMiddleware($request);

    expect($request->getMethod())->toBe($originalMethod);
    expect($request->getPathInfo())->toBe($originalPath);
    expect($request->query->all())->toBe($originalQuery);
    expect($request->getContent())->toBe($originalBody);
    expect($request->headers->get('X-Custom'))->toBe($originalCustomHeader);
    expect($request->headers->get('Accept'))->toBe('application/json');
    expect($request->headers->get('X-Original-Accept'))->toBe('*/*');
});

function runMiddleware(Request $request): void
{
    (new PrefersJsonResponses)->handle($request, fn ($request) => new Response);
}
