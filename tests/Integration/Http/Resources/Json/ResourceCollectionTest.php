<?php

namespace Venusian\Serve\Tests\Integration\Http\Resources\Json;

use Mariner\Auth\User;
use Mariner\Http\Request;
use Mariner\Http\Resources\Json\ResourceCollection;
use Voyager\NutsAndBolts\Fluent;

test('it can return to array', function (ResourceCollection $collection, mixed $expected) {
    $request = Request::create('GET', '/');

    expect($collection->toArray($request))->toBe($expected);
})->with('toArrayDataProvider');

dataset('toArrayDataProvider', function () {
    yield [
        new ResourceCollection([
            new Fluent(['id' => 1]),
            new Fluent(['id' => 2]),
            new Fluent(['id' => 3]),
        ]),
        [
            ['id' => 1],
            ['id' => 2],
            ['id' => 3],
        ],
    ];

    yield [
        (new ResourceCollection([
            (new User())->forceFill(['name' => 'Taylor Otwell']),
            (new User())->forceFill(['name' => 'Laravel']),
        ]))->additional(['total', 1]),
        [
            ['name' => 'Taylor Otwell'],
            ['name' => 'Laravel'],
        ],
    ];

    yield [
        new class(['list' => new Fluent(['id' => 1]), 'total' => 1]) extends ResourceCollection
        {
            public function toArray(Request $request)
            {
                return $this->resource->toArray();
            }
        },
        [
            'list' => ['id' => 1],
            'total' => 1,
        ],
    ];
});
