<?php

namespace Venusian\Serve\Tests\Integration\Http;

use Mariner\Http\Response;
use Mariner\NutsAndBolts\Facades\Route;
use InvalidArgumentException;
use JsonSerializable;

test('response with invalid json throws exception', function () {

    Route::get('/response', function () {
        return (new Response())->setContent(new class implements JsonSerializable
        {
            public function jsonSerialize(): string
            {
                return "\xB1\x31";
            }
        });
    });

    $this->withoutExceptionHandling();

    $this->get('/response');
})->throws(InvalidArgumentException::class, 'Malformed UTF-8 characters, possibly incorrectly encoded');
