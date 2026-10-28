<?php

namespace Venusian\Serve\Tests\JsonSchema;

use Mariner\JsonSchema\JsonSchema;

test('serializes as boolean with metadata', function () {
    $type = JsonSchema::boolean()->title('Enabled')->description('Feature flag');

    expect($type->toArray())->toEqual([
        'type' => 'boolean',
        'title' => 'Enabled',
        'description' => 'Feature flag',
    ]);
});

test('may set default true via default', function () {
    $type = JsonSchema::boolean()->default(true);

    expect($type->toArray())->toEqual([
        'type' => 'boolean',
        'default' => true,
    ]);
});

test('may set default false via default', function () {
    $type = JsonSchema::boolean()->default(false);

    expect($type->toArray())->toEqual([
        'type' => 'boolean',
        'default' => false,
    ]);
});

test('may set enum', function () {
    $type = JsonSchema::boolean()->enum([true, false]);

    expect($type->toArray())->toEqual([
        'type' => 'boolean',
        'enum' => [true, false],
    ]);
});
