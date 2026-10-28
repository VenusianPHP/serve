<?php

namespace Venusian\Serve\Tests\JsonSchema;

use Mariner\JsonSchema\JsonSchema;

test('it may set min value as float', function () {
    $type = JsonSchema::number()->title('Price')->min(5.5);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'title' => 'Price',
        'minimum' => 5.5,
    ]);
});

test('it may set min value as int', function () {
    $type = JsonSchema::number()->title('Price')->min(5);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'title' => 'Price',
        'minimum' => 5,
    ]);
});

test('it may set max value as float', function () {
    $type = JsonSchema::number()->description('Max price')->max(10.75);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'description' => 'Max price',
        'maximum' => 10.75,
    ]);
});

test('it may set max value as int', function () {
    $type = JsonSchema::number()->description('Max price')->max(10);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'description' => 'Max price',
        'maximum' => 10,
    ]);
});

test('it may set default value', function () {
    $type = JsonSchema::number()->default(9.99);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'default' => 9.99,
    ]);
});

test('it may set multiple of as float', function () {
    $type = JsonSchema::number()->multipleOf(0.5);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'multipleOf' => 0.5,
    ]);
});

test('it may set multiple of as int', function () {
    $type = JsonSchema::number()->multipleOf(3);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'multipleOf' => 3,
    ]);
});

test('it may combine multiple of with min and max', function () {
    $type = JsonSchema::number()->min(0.0)->max(10.0)->multipleOf(0.25);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'minimum' => 0.0,
        'maximum' => 10.0,
        'multipleOf' => 0.25,
    ]);
});

test('it may set enum', function () {
    $type = JsonSchema::number()->enum([1, 2.5, 3]);

    expect($type->toArray())->toEqual([
        'type' => 'number',
        'enum' => [1, 2.5, 3],
    ]);
});
