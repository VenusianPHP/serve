<?php

namespace Venusian\Serve\Tests\View;

use Mariner\View\Engines\EngineResolver;
use InvalidArgumentException;
use stdClass;

test('resolvers may be resolved', function () {
    $resolver = new EngineResolver;
    $resolver->register('foo', function () {
        return new stdClass;
    });
    $result = $resolver->resolve('foo');

    expect(spl_object_id($resolver->resolve('foo')))->toEqual(spl_object_id($result));
});

test('resolver throws exception on unknown engine', function () {

    $resolver = new EngineResolver;
    $resolver->resolve('foo');
})->throws(InvalidArgumentException::class);
