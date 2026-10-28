<?php

namespace Venusian\Serve\Tests\JsonSchema;

use Mariner\JsonSchema\Types\Type;
use RuntimeException;

test('it does not know how to serialize unknown types', function () {

    $type = new class extends Type {
        // anonymous type for triggering serializer failure
    };

    $type->toArray();
})->throws(RuntimeException::class, 'Unsupported [Mariner\\JsonSchema\\Types\\Type@anonymous');
