<?php

namespace Venusian\Serve\Tests\Integration\Http;

use Voyager\Contracts\NutsAndBolts\Jsonable;
use Mariner\Http\JsonResponse;
use Mariner\NutsAndBolts\Facades\Route;
use InvalidArgumentException;
use JsonSerializable;

test('response with invalid json throws exception', function () {

    Route::get('/response', function () {
        return new JsonResponse(new class implements JsonSerializable
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

test('response set data passes with prior json errors', function () {
    $response = new JsonResponse();

    // Trigger json_last_error() to have a non-zero value...
    json_encode(['a' => acos(2)]);

    $response->setData(new class implements Jsonable
    {
        public function toJson($options = 0): string
        {
            return '{}';
        }
    });

    expect($response->getContent())->toBeJson();
});
