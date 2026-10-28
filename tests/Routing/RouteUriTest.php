<?php

namespace Venusian\Serve\Tests\Routing;

use Mariner\Routing\RouteUri;

test('route uris are properly parsed', function ($uri, $expectedParsedUri, $expectedBindingFields) {
    $parsed = RouteUri::parse($uri);
    expect($parsed->uri)->toBe($expectedParsedUri);
    expect($parsed->bindingFields)->toEqual($expectedBindingFields);
})->with('uriProvider');

dataset('uriProvider', function () {
    return [
        [
            '/foo',
            '/foo',
            [],
        ],
        [
            '/foo/{bar}',
            '/foo/{bar}',
            [],
        ],
        [
            '/foo/{bar}/baz/{qux}',
            '/foo/{bar}/baz/{qux}',
            [],
        ],
        [
            '/foo/{bar}/baz/{qux?}',
            '/foo/{bar}/baz/{qux?}',
            [],
        ],
        [
            '/foo/{bar:slug}',
            '/foo/{bar}',
            ['bar' => 'slug'],
        ],
        [
            '/foo/{bar}/baz/{qux:slug}',
            '/foo/{bar}/baz/{qux}',
            ['qux' => 'slug'],
        ],
        [
            '/foo/{bar}/baz/{qux:slug}',
            '/foo/{bar}/baz/{qux}',
            ['qux' => 'slug'],
        ],
        [
            '/foo/{bar}/baz/{qux:slug?}',
            '/foo/{bar}/baz/{qux?}',
            ['qux' => 'slug'],
        ],
        [
            '/foo/{bar}/baz/{qux:slug?}/{test:id?}',
            '/foo/{bar}/baz/{qux?}/{test?}',
            ['qux' => 'slug', 'test' => 'id'],
        ],
    ];
});
