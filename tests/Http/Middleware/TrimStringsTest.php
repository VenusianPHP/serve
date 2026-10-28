<?php

namespace Venusian\Serve\Tests\Http\Middleware;

use Mariner\Http\Middleware\TrimStrings;
use Mariner\Http\Request;

test('no zero width space character returns the same string', function () {
    $request = new Request;

    $request->merge([
        'title' => 'This title does not contain any zero-width space',
    ]);

    $middleware = new TrimStrings;

    $middleware->handle($request, function ($req) {
        expect($req->title)->toBe('This title does not contain any zero-width space');
    });
});

test('leading zero width space character is trimmed', function () {
    $request = new Request;

    $request->merge([
        'title' => '​This title contains a zero-width space at the beginning',
    ]);

    $middleware = new TrimStrings;

    $middleware->handle($request, function ($req) {
        expect($req->title)->toBe('This title contains a zero-width space at the beginning');
    });
});

test('trim strings can globally ignore certain inputs', function () {
    $request = new Request;

    $request->merge([
        'globally_ignored_title' => ' test title ',
    ]);

    TrimStrings::except(['globally_ignored_title']);

    $middleware = new TrimStrings;

    $middleware->handle($request, function ($req) {
        expect($req->globally_ignored_title)->toBe(' test title ');
    });
});

test('trailing zero width space character is trimmed', function () {
    $request = new Request;

    $request->merge([
        'title' => 'This title contains a zero-width space at the end​',
    ]);

    $middleware = new TrimStrings;

    $middleware->handle($request, function ($req) {
        expect($req->title)->toBe('This title contains a zero-width space at the end');
    });
});

test('leading zero width non breakable space character is trimmed', function () {
    $request = new Request;

    $request->merge([
        'title' => '﻿This title contains a zero-width non-breakable space at the beginning',
    ]);

    $middleware = new TrimStrings;

    $middleware->handle($request, function ($req) {
        expect($req->title)->toBe('This title contains a zero-width non-breakable space at the beginning');
    });
});

test('leading multiple zero width non breakable space characters are trimmed', function () {
    $request = new Request;

    $request->merge([
        'title' => '﻿﻿This title contains a zero-width non-breakable space at the beginning',
    ]);

    $middleware = new TrimStrings;

    $middleware->handle($request, function ($req) {
        expect($req->title)->toBe('This title contains a zero-width non-breakable space at the beginning');
    });
});

test('combination of leading and trailing zero width non breakable space and zero width space characters are trimmed', function () {
    $request = new Request;

    $request->merge([
        'title' => '﻿​﻿This title contains a combination of zero-width non-breakable space and zero-width spaces characters at the beginning and the end​',
    ]);

    $middleware = new TrimStrings;

    $middleware->handle($request, function ($req) {
        expect($req->title)->toBe('This title contains a combination of zero-width non-breakable space and zero-width spaces characters at the beginning and the end');
    });
});

test('leading invisible characters are trimmed', function () {
    $request = new Request;

    $request->merge([
        'title' => '‎This title contains a invisible character at the beginning',
    ]);

    $middleware = new TrimStrings;

    $middleware->handle($request, function ($req) {
        expect($req->title)->toBe('This title contains a invisible character at the beginning');
    });
});

test('trailing invisible characters are trimmed', function () {
    $request = new Request;

    $request->merge([
        'title' => 'This title contains a invisible character at the end‎',
    ]);

    $middleware = new TrimStrings;

    $middleware->handle($request, function ($req) {
        expect($req->title)->toBe('This title contains a invisible character at the end');
    });
});

test('leading multiple invisible characters are trimmed', function () {
    $request = new Request;

    $request->merge([
        'title' => '‎‎This title contains a invisible character at the beginning',
    ]);

    $middleware = new TrimStrings;

    $middleware->handle($request, function ($req) {
        expect($req->title)->toBe('This title contains a invisible character at the beginning');
    });
});

test('trailing multiple invisible characters are trimmed', function () {
    $request = new Request;

    $request->merge([
        'title' => 'This title contains a invisible character at the end‎‎',
    ]);

    $middleware = new TrimStrings;

    $middleware->handle($request, function ($req) {
        expect($req->title)->toBe('This title contains a invisible character at the end');
    });
});

test('combination of leading and trailing multiple invisible characters are trimmed', function () {
    $request = new Request;

    $request->merge([
        'title' => '‎‎This title contains a combination of a invisible character at beginning and the end‎‎',
    ]);

    $middleware = new TrimStrings;

    $middleware->handle($request, function ($req) {
        expect($req->title)->toBe('This title contains a combination of a invisible character at beginning and the end');
    });
});

test('trim strings can ignore nested attributes using wildcards', function () {
    $request = new Request;

    $request->merge([
        'users' => [
            ['name' => '  foo  ', 'role' => '  admin  '],
            ['name' => '  bar  ', 'role' => '  editor  '],
        ],
        'teams' => [
            ['name' => '  team  '],
        ],
        'orders' => [
            [
                'items' => [
                    ['meta' => ['title' => '  foo  ', 'sku' => '  SKU-1  ', 'tags' => ['  alpha  ']]],
                ],
            ],
            [
                'items' => [
                    ['meta' => ['title' => '  bar  ', 'sku' => '  SKU-2  ', 'tags' => ['  beta  ']]],
                ],
            ],
        ],
    ]);

    $middleware = new class extends TrimStrings
    {
        protected $except = [
            'users.*.name',
            'orders.*.items.*.meta.title',
            'orders.*.items.*.meta.tags.*',
        ];
    };

    $middleware->handle($request, function ($req) {
        expect($req->input('users.0.name'))->toBe('  foo  ');
        expect($req->input('users.1.name'))->toBe('  bar  ');
        expect($req->input('users.0.role'))->toBe('admin');
        expect($req->input('users.1.role'))->toBe('editor');
        expect($req->input('teams.0.name'))->toBe('team');
        expect($req->input('orders.0.items.0.meta.title'))->toBe('  foo  ');
        expect($req->input('orders.0.items.0.meta.sku'))->toBe('SKU-1');
        expect($req->input('orders.0.items.0.meta.tags.0'))->toBe('  alpha  ');

        expect($req->input('orders.1.items.0.meta.title'))->toBe('  bar  ');
        expect($req->input('orders.1.items.0.meta.sku'))->toBe('SKU-2');
        expect($req->input('orders.1.items.0.meta.tags.0'))->toBe('  beta  ');
    });
});
