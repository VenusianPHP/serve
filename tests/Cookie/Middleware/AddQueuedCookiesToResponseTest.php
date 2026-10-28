<?php

namespace Venusian\Serve\Tests\Cookie\Middleware;

use Mariner\Cookie\CookieJar;
use Mariner\Cookie\Middleware\AddQueuedCookiesToResponse;
use Mariner\Http\Request;
use Mariner\Http\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;

test('handle', function () {
    $cookieJar = new CookieJar;
    $cookieOne = $cookieJar->make('foo', 'bar', 0, '/path');
    $cookieTwo = $cookieJar->make('foo', 'rab', 0, '/');
    $cookieJar->queue($cookieOne);
    $cookieJar->queue($cookieTwo);
    $addQueueCookiesToResponseMiddleware = new AddQueuedCookiesToResponse($cookieJar);
    $next = function (Request $request) {
        return new Response;
    };
    expect($addQueueCookiesToResponseMiddleware->handle(new Request, $next)->headers->getCookies(ResponseHeaderBag::COOKIES_ARRAY))->toEqual([
        '' => [
            '/path' => [
                'foo' => $cookieOne,
            ],
            '/' => [
                'foo' => $cookieTwo,
            ],
        ],
    ]);
});
