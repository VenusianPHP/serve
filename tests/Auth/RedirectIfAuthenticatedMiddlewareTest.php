<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\Middleware\RedirectIfAuthenticated;

test('it can generate definition via static method', function () {
    $signature = RedirectIfAuthenticated::using('foo');
    expect($signature)->toBe('Mariner\Auth\Middleware\RedirectIfAuthenticated:foo');

    $signature = RedirectIfAuthenticated::using('foo', 'bar');
    expect($signature)->toBe('Mariner\Auth\Middleware\RedirectIfAuthenticated:foo,bar');

    $signature = RedirectIfAuthenticated::using('foo', 'bar', 'baz');
    expect($signature)->toBe('Mariner\Auth\Middleware\RedirectIfAuthenticated:foo,bar,baz');
});
