<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\Middleware\EnsureEmailIsVerified;

test('it can generate definition via static method', function () {
    $signature = EnsureEmailIsVerified::redirectTo('route.name');
    expect($signature)->toBe('Mariner\Auth\Middleware\EnsureEmailIsVerified:route.name');
});
