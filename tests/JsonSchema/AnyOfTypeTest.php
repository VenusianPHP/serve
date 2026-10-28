<?php

namespace Venusian\Serve\Tests\JsonSchema;

use Mariner\JsonSchema\JsonSchema;

test('it may describe any of multiple schemas', function () {
    $type = JsonSchema::anyOf([
        JsonSchema::string(),
        JsonSchema::integer(),
    ])->title('Identifier');

    expect($type->toArray())->toEqual([
        'title' => 'Identifier',
        'anyOf' => [
            ['type' => 'string'],
            ['type' => 'integer'],
        ],
    ]);
});

test('it may be initialized with a closure', function () {
    $type = JsonSchema::anyOf(fn (JsonSchema $schema): array => [
        $schema->string(),
        $schema->integer(),
    ]);

    expect($type->toArray())->toEqual([
        'anyOf' => [
            ['type' => 'string'],
            ['type' => 'integer'],
        ],
    ]);
});

test('it may be nullable', function () {
    $type = JsonSchema::anyOf([
        JsonSchema::string(),
        JsonSchema::integer(),
    ])->nullable();

    expect($type->toArray())->toEqual([
        'anyOf' => [
            ['type' => 'string'],
            ['type' => 'integer'],
            ['type' => 'null'],
        ],
    ]);
});

test('it may describe object unions', function () {
    $type = JsonSchema::anyOf([
        JsonSchema::object([
            'type' => JsonSchema::string()->enum(['article'])->required(),
            'title' => JsonSchema::string()->required(),
            'content' => JsonSchema::string()->required(),
        ]),
        JsonSchema::object([
            'type' => JsonSchema::string()->enum(['image'])->required(),
            'url' => JsonSchema::string()->required(),
            'caption' => JsonSchema::string(),
        ]),
    ]);

    expect($type->toArray())->toEqual([
        'anyOf' => [
            [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['article']],
                    'title' => ['type' => 'string'],
                    'content' => ['type' => 'string'],
                ],
                'required' => ['type', 'title', 'content'],
            ],
            [
                'type' => 'object',
                'properties' => [
                    'type' => ['type' => 'string', 'enum' => ['image']],
                    'url' => ['type' => 'string'],
                    'caption' => ['type' => 'string'],
                ],
                'required' => ['type', 'url'],
            ],
        ],
    ]);
});
