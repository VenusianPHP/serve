<?php

namespace Venusian\Serve\Tests\Http\Middleware;

use Mariner\View\ViteState;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Vessel\ControlPanel;
use Mariner\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Mariner\Http\Request;
use Mariner\Http\Response;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

beforeEach(fn () => $this->app = WebApp::boot());
afterEach(fn () => WebApp::tearDown($this));

function preloaded(array $assets): void
{
    ControlPanel::getInstance()->make(ViteState::class)->preloadedAssets = $assets;
}

test('it does not set link tag when no tags have been preloaded', function () {
    preloaded([]);

    $response = (new AddLinkHeadersForPreloadedAssets)->handle(new Request, function () {
        return new Response('Hello Laravel');
    });

    expect($response->headers->get('Link'))->toBeNull();
});

test('it adds preload link header', function () {
    preloaded([
        'https://laravel.com/app.js' => [
            'rel="modulepreload"',
            'foo="bar"',
        ],
    ]);

    $response = (new AddLinkHeadersForPreloadedAssets)->handle(new Request, function () {
        return new Response('Hello Laravel');
    });

    expect($response->headers->get('Link'))->toBe('<https://laravel.com/app.js>; rel="modulepreload"; foo="bar"');
});

test('it does not attach headers to non mariner responses', function () {
    preloaded([
        'https://laravel.com/app.js' => [
            'rel="modulepreload"',
            'foo="bar"',
        ],
    ]);

    $response = (new AddLinkHeadersForPreloadedAssets)->handle(new Request, function () {
        return new SymfonyResponse('Hello Laravel');
    });

    expect($response->headers->get('Link'))->toBeNull();
});

test('it does not overwrite other link headers', function () {
    preloaded([
        'https://laravel.com/app.js' => [
            'rel="modulepreload"',
            'foo="bar"',
        ],
    ]);

    $response = (new AddLinkHeadersForPreloadedAssets)->handle(new Request, function () {
        return new Response('Hello Laravel', headers: ['Link' => '<https://laravel.com/logo.png>; rel="preload"; as="image"']);
    });

    expect($response->headers->all('Link'))->toBe([
        '<https://laravel.com/logo.png>; rel="preload"; as="image"',
        '<https://laravel.com/app.js>; rel="modulepreload"; foo="bar"',
    ]);
});

test('it can limit number of assets preloaded', function () {
    preloaded([
        'https://laravel.com/first.js' => [
            'rel="modulepreload"',
            'foo="bar"',
        ],
        'https://laravel.com/second.js' => [
            'rel="modulepreload"',
            'foo="bar"',
        ],
        'https://laravel.com/third.js' => [
            'rel="modulepreload"',
            'foo="bar"',
        ],
        'https://laravel.com/fourth.js' => [
            'rel="modulepreload"',
            'foo="bar"',
        ],
    ]);

    $response = (new AddLinkHeadersForPreloadedAssets)->handle(new Request, fn () => new Response('ok'), 2);

    expect($response->headers->all('Link'))->toBe([
        '<https://laravel.com/first.js>; rel="modulepreload"; foo="bar", <https://laravel.com/second.js>; rel="modulepreload"; foo="bar"',
    ]);
});

test('font preload entries result in link headers', function () {
    preloaded([
        'https://example.com/build/assets/inter-400.woff2' => [
            'rel="preload"',
            'as="font"',
            'type="font/woff2"',
            'crossorigin="anonymous"',
        ],
    ]);

    $response = (new AddLinkHeadersForPreloadedAssets)->handle(new Request, function () {
        return new Response('Hello Laravel');
    });

    expect($response->headers->get('Link'))->toBe('<https://example.com/build/assets/inter-400.woff2>; rel="preload"; as="font"; type="font/woff2"; crossorigin="anonymous"');
});

test('font preloads do not overwrite existing js preloads', function () {
    preloaded([
        'https://example.com/build/assets/app.js' => [
            'rel="modulepreload"',
        ],
        'https://example.com/build/assets/inter-400.woff2' => [
            'rel="preload"',
            'as="font"',
            'type="font/woff2"',
            'crossorigin="anonymous"',
        ],
    ]);

    $response = (new AddLinkHeadersForPreloadedAssets)->handle(new Request, function () {
        return new Response('Hello Laravel');
    });

    expect($response->headers->all('Link'))->toBe([
        '<https://example.com/build/assets/app.js>; rel="modulepreload", <https://example.com/build/assets/inter-400.woff2>; rel="preload"; as="font"; type="font/woff2"; crossorigin="anonymous"',
    ]);
});

test('limit applies to combined js and font preloads', function () {
    preloaded([
        'https://example.com/build/assets/app.js' => [
            'rel="modulepreload"',
        ],
        'https://example.com/build/assets/inter-400.woff2' => [
            'rel="preload"',
            'as="font"',
        ],
        'https://example.com/build/assets/inter-700.woff2' => [
            'rel="preload"',
            'as="font"',
        ],
    ]);

    $response = (new AddLinkHeadersForPreloadedAssets)->handle(new Request, fn () => new Response('ok'), 2);

    expect($response->headers->all('Link'))->toBe([
        '<https://example.com/build/assets/app.js>; rel="modulepreload", <https://example.com/build/assets/inter-400.woff2>; rel="preload"; as="font"',
    ]);
});

test('it can configure the middleware', function () {
    $definition = AddLinkHeadersForPreloadedAssets::using(limit: 5);

    expect($definition)->toBe('Mariner\Http\Middleware\AddLinkHeadersForPreloadedAssets:5');
});
