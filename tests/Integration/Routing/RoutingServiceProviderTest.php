<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

test('resolving psr request', function () {
    $psrRequest = $this->app->make(ServerRequestInterface::class);

    expect($psrRequest)->toBeInstanceOf(ServerRequestInterface::class);
});

test('resolving psr response', function () {
    $psrResponse = $this->app->make(ResponseInterface::class);

    expect($psrResponse)->toBeInstanceOf(ResponseInterface::class);
});
