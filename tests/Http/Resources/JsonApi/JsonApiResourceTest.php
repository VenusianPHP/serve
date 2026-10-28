<?php

namespace Venusian\Serve\Tests\Http\Resources\JsonApi;

use BadMethodCallException;
use Mariner\Http\Resources\Json\JsonResource;
use Mariner\Http\Resources\JsonApi\JsonApiResource;

afterEach(function () {
    JsonResource::flushState();
    JsonApiResource::flushState();
});

test('response wrapper is hard coded to data', function () {
    JsonResource::wrap('laravel');

    expect(JsonApiResource::$wrap)->toBe('data');
});

test('unable to set wrapper', function () {

    JsonApiResource::wrap('laravel');
})->throws(BadMethodCallException::class, 'Using Mariner\Http\Resources\JsonApi\JsonApiResource::wrap() method is not allowed.');

test('unable to unset wrapper', function () {

    JsonApiResource::withoutWrapping();
})->throws(BadMethodCallException::class, 'Using Mariner\Http\Resources\JsonApi\JsonApiResource::withoutWrapping() method is not allowed.');

test('flush state resets max relationship depth to default', function () {
    expect(JsonApiResource::$maxRelationshipDepth)->toBe(5);

    JsonApiResource::maxRelationshipDepth(10);
    expect(JsonApiResource::$maxRelationshipDepth)->toBe(10);

    JsonApiResource::flushState();

    expect(JsonApiResource::$maxRelationshipDepth)->toBe(5);
});
