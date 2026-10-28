<?php

namespace Venusian\Serve\Tests\Http;

use Voyager\Contracts\NutsAndBolts\Arrayable;
use Voyager\Contracts\NutsAndBolts\Jsonable;
use Mariner\Http\JsonResponse;
use InvalidArgumentException;
use JsonSerializable;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;

test('set and retrieve data', function ($data) {
    $response = new JsonResponse($data);

    expect($response->getData())->toBeInstanceOf(stdClass::class);
    expect($response->getData()->foo)->toBe('bar');
})->with('setAndRetrieveDataProvider');

dataset('setAndRetrieveDataProvider', function () {
    return [
        'Jsonable data' => [new JsonResponseTestJsonableObject],
        'JsonSerializable data' => [new JsonResponseTestJsonSerializeObject],
        'Arrayable data' => [new JsonResponseTestArrayableObject],
        'Array data' => [['foo' => 'bar']],
        'stdClass data' => [(object) ['foo' => 'bar']],
    ];
});

test('get original content', function () {
    $response = new JsonResponse(new JsonResponseTestArrayableObject);
    expect($response->getOriginalContent())->toBeInstanceOf(JsonResponseTestArrayableObject::class);

    $response = new JsonResponse;
    $response->setData(new JsonResponseTestArrayableObject);
    expect($response->getOriginalContent())->toBeInstanceOf(JsonResponseTestArrayableObject::class);
});

test('set and retrieve options', function () {
    $response = new JsonResponse(['foo' => 'bar']);
    $response->setEncodingOptions(JSON_PRETTY_PRINT);
    expect($response->getEncodingOptions())->toBe(JSON_PRETTY_PRINT);
});

test('set and retrieve default options', function () {
    $response = new JsonResponse(['foo' => 'bar']);
    expect($response->getEncodingOptions())->toBe(0);
});

test('set and retrieve status code', function () {
    $response = new JsonResponse(['foo' => 'bar'], 404);
    expect($response->getStatusCode())->toBe(404);

    $response = new JsonResponse(['foo' => 'bar']);
    $response->setStatusCode(404);
    expect($response->getStatusCode())->toBe(404);
});

test('invalid argument exception on json error', function ($data) {
    new JsonResponse(['data' => $data]);
})->with('jsonErrorDataProvider')->throws(InvalidArgumentException::class);

test('gracefully handled some json errors with partial output on error', function ($data) {
    $response = new JsonResponse(['data' => $data], 200, [], JSON_PARTIAL_OUTPUT_ON_ERROR);

    expect(json_decode($response->getContent(), true))->toHaveKey('data');
})->with('jsonErrorDataProvider');

dataset('jsonErrorDataProvider', function () {
    // Resources can't be encoded
    $resource = tmpfile();

    // Recursion can't be encoded
    $recursiveObject = new stdClass;
    $objectB = new stdClass;
    $recursiveObject->b = $objectB;
    $objectB->a = $recursiveObject;

    // NAN or INF can't be encoded
    $nan = NAN;

    return [
        [$resource],
        [$recursiveObject],
        [$nan],
    ];
});

test('from json string', function () {
    $json_string = '{"foo":"bar"}';
    $response = JsonResponse::fromJsonString($json_string);

    expect($response->getData()->foo)->toBe('bar');
});

class JsonResponseTestJsonableObject implements Jsonable
{
    public function toJson(int $options = 0): string
    {
        return '{"foo":"bar"}';
    }
}

class JsonResponseTestJsonSerializeObject implements JsonSerializable
{
    public function jsonSerialize(): array
    {
        return ['foo' => 'bar'];
    }
}

class JsonResponseTestArrayableObject implements Arrayable
{
    public function toArray()
    {
        return ['foo' => 'bar'];
    }
}
