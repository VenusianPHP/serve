<?php

namespace Venusian\Serve\Tests\Routing;

use Mariner\Contracts\View\Factory as ViewFactory;
use Mariner\Routing\Redirector;
use Mariner\Routing\ResponseFactory;
use Mockery;

test('stream download filename without ascii equivalent', function () {
    $factory = new ResponseFactory(Mockery::mock(ViewFactory::class), Mockery::mock(Redirector::class));
    $response = $factory->streamDownload(fn () => null, '請求書');
    expect($response->headers->get('content-disposition'))->toBe('attachment; filename=___; filename*=utf-8\'\'%E8%AB%8B%E6%B1%82%E6%9B%B8');
});
