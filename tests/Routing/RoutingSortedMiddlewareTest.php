<?php

namespace Venusian\Serve\Tests\Routing;

use Mariner\Routing\SortedMiddleware;

test('middleware can be sorted by priority', function () {
    $priority = [
        'First',
        'Second',
        'Third',
    ];

    $middleware = [
        'Something',
        'Something',
        'Something',
        'Something',
        'Second',
        'Otherthing',
        'First:api',
        'Third:foo',
        'First:foo,bar',
        'Third',
        'Second',
    ];

    $expected = [
        'Something',
        'First:api',
        'First:foo,bar',
        'Second',
        'Otherthing',
        'Third:foo',
        'Third',
    ];

    expect((new SortedMiddleware($priority, $middleware))->all())->toEqual($expected);

    expect((new SortedMiddleware(['First'], []))->all())->toBe([]);
    expect((new SortedMiddleware(['First'], ['First']))->all())->toEqual(['First']);
    expect((new SortedMiddleware(['First', 'Second'], ['Second', 'First']))->all())->toEqual(['First', 'Second']);
});

test('it does not move non string values', function () {
    $closure = function () {
        return 'foo';
    };

    $closure2 = function () {
        return 'bar';
    };

    expect((new SortedMiddleware([1, 2], [2, 1]))->all())->toEqual([2, 1]);
    expect((new SortedMiddleware(['First', 'Second'], ['Second', $closure]))->all())->toEqual(['Second', $closure]);
    expect((new SortedMiddleware(['a', 'b'], ['b', $closure, 'a']))->all())->toEqual(['a', 'b', $closure]);
    expect((new SortedMiddleware(['a', 'b'], [$closure2, 'b', $closure, 'a', 'foo']))->all())->toEqual([$closure2, 'a', 'b', $closure, 'foo']);
    expect((new SortedMiddleware(['a', 'b'], [$closure, 'b', $closure2, 'foo', 'a']))->all())->toEqual([$closure, 'a', 'b', $closure2, 'foo']);
    expect((new SortedMiddleware(['a', 'b'], ['a', $closure, 'b', $closure2, 'foo']))->all())->toEqual(['a', $closure, 'b', $closure2, 'foo']);
    expect((new SortedMiddleware(['a', 'b'], [$closure, $closure2, 'foo', 'a']))->all())->toEqual([$closure, $closure2, 'foo', 'a']);
});

test('it sorts using parents and contracts', function () {
    $priority = [
        FirstContractStub::class,
        SecondStub::class,
        'Third',
    ];

    $middleware = [
        'Something',
        'Something',
        'Something',
        'Something',
        SecondChildStub::class,
        'Otherthing',
        FirstStub::class.':api',
        'Third:foo',
        FirstStub::class.':foo,bar',
        'Third',
        SecondChildStub::class,
    ];

    $expected = [
        'Something',
        FirstStub::class.':api',
        FirstStub::class.':foo,bar',
        SecondChildStub::class,
        'Otherthing',
        'Third:foo',
        'Third',
    ];

    expect((new SortedMiddleware($priority, $middleware))->all())->toEqual($expected);
});

interface FirstContractStub
{
    //
}

class FirstStub implements FirstContractStub
{
    //
}

class SecondStub
{
    //
}

class SecondChildStub extends SecondStub
{
    //
}
