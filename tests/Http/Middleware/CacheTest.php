<?php

namespace Venusian\Serve\Tests\Http\Middleware;

use Mariner\Http\Middleware\SetCacheHeaders as Cache;
use Mariner\Http\Request;
use Mariner\Http\Response;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

test('it can generate definition via static method', function () {
    $signature = (string) Cache::using('max_age=120;no-transform;s_maxage=60;');
    expect($signature)->toBe('Mariner\Http\Middleware\SetCacheHeaders:max_age=120;no-transform;s_maxage=60;');

    $signature = (string) Cache::using('max_age=120;no-transform;s_maxage=60');
    expect($signature)->toBe('Mariner\Http\Middleware\SetCacheHeaders:max_age=120;no-transform;s_maxage=60');

    $signature = (string) Cache::using([
        'max_age=120',
        'no-transform',
        's_maxage=60',
        'etag' => true,
    ]);
    expect($signature)->toBe('Mariner\Http\Middleware\SetCacheHeaders:max_age=120;no-transform;s_maxage=60;etag');

    $signature = (string) Cache::using([
        'max_age' => 120,
        'no-transform',
        's_maxage' => '60',
    ]);
    expect($signature)->toBe('Mariner\Http\Middleware\SetCacheHeaders:max_age=120;no-transform;s_maxage=60');
});

test('do not set header when method not cacheable', function () {
    $request = new Request;
    $request->setMethod('PUT');

    $response = (new Cache)->handle($request, function () {
        return new Response('Hello Laravel');
    }, 'max_age=120;s_maxage=60');

    expect($response->getMaxAge())->toBeNull();
});

test('do not set header when no content', function () {
    $response = (new Cache)->handle(new Request, function () {
        return new Response;
    }, 'max_age=120;s_maxage=60');

    expect($response->getMaxAge())->toBeNull();
    expect($response->getEtag())->toBeNull();
});

test('set header to file response even with no content', function () {
    $response = (new Cache)->handle(new Request, function () {
        $filePath = __DIR__.'/../Fixtures/test.txt';

        return new BinaryFileResponse($filePath);
    }, 'max_age=120;s_maxage=60');

    expect($response->getMaxAge())->not->toBeNull();
});

test('set header to download response even with no content', function () {
    $response = (new Cache)->handle(new Request, function () {
        return new StreamedResponse(function () {
            $filePath = __DIR__.'/../Fixtures/test.txt';
            readfile($filePath);
        });
    }, 'max_age=120;s_maxage=60');

    expect($response->getMaxAge())->not->toBeNull();
});

test('add headers', function () {
    $response = (new Cache)->handle(new Request, function () {
        return new Response('some content');
    }, 'max_age=100;s_maxage=200;etag=ABC');

    expect($response->getEtag())->toBe('"ABC"');
    expect($response->headers->get('Cache-Control'))->toBe('max-age=100, public, s-maxage=200');
});

test('add cache headers for method head', function () {
    $request = new Request;
    $request->setMethod('HEAD');
    $response = (new Cache)->handle($request, function () {
        return new Response;
    }, 'max_age=120;s_maxage=60');

    expect($response->getMaxAge())->not->toBeNull();
});

test('add headers using array', function () {
    $response = (new Cache)->handle(new Request, function () {
        return new Response('some content');
    }, ['max_age' => 100, 's_maxage' => 200, 'etag' => 'ABC']);

    expect($response->getEtag())->toBe('"ABC"');
    expect($response->headers->get('Cache-Control'))->toBe('max-age=100, public, s-maxage=200');
});

test('generate etag', function () {
    $response = (new Cache)->handle(new Request, function () {
        return new Response('some content');
    }, 'etag;max_age=100;s_maxage=200');

    expect($response->getEtag())->toBe('"4f1b32bff4356281946800d355007128"');
    expect($response->headers->get('Cache-Control'))->toBe('max-age=100, public, s-maxage=200');
});

test('does not override etag', function () {
    $response = (new Cache)->handle(new Request, function () {
        return (new Response('some content'))->setEtag('XYZ');
    }, 'etag');

    expect($response->getEtag())->toBe('"XYZ"');
});

test('is not modified', function () {
    $request = new Request;
    $request->headers->set('If-None-Match', '"4f1b32bff4356281946800d355007128"');

    $response = (new Cache)->handle($request, function () {
        return new Response('some content');
    }, 'etag;max_age=100;s_maxage=200');

    expect($response->getStatusCode())->toBe(304);
});

test('invalid option', function () {

    (new Cache)->handle(new Request, function () {
        return new Response('some content');
    }, 'invalid');
})->throws(InvalidArgumentException::class);

test('last modified unix time', function () {
    $time = time();

    $response = (new Cache)->handle(new Request, function () {
        return new Response('some content');
    }, "last_modified=$time");

    expect($response->getLastModified()->getTimestamp())->toBe($time);
});

test('last modified string date', function () {
    $birthdate = '1973-04-09 10:10:10';
    $response = (new Cache)->handle(new Request, function () {
        return new Response('some content');
    }, "last_modified=$birthdate");

    expect($response->getLastModified()->getTimestamp())->toBe(Carbon::parse($birthdate)->getTimestamp());
});

test('trailing delimiter ignored', function () {
    $time = time();

    $response = (new Cache)->handle(new Request, function () {
        return new Response('some content');
    }, "last_modified=$time;");

    expect($response->getLastModified()->getTimestamp())->toBe($time);
});

test('it does not set etag headers for binary content', function () {
    $response = (new Cache)->handle(new Request, function () {
        return new BinaryFileResponse(__DIR__.'/../Fixtures/test.txt');
    }, 'etag');

    expect($response->getEtag())->toBeNull();
});
