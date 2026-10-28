<?php

namespace Venusian\Serve\Tests\Http;

use Voyager\Vessel\ControlPanel;
use Voyager\Database\Instrument\Model;
use Mariner\Http\Request;
use Mariner\Http\Resources\Json\JsonResource;
use Mariner\Http\Resources\MissingValue;
use Mockery;
use Venusian\Serve\Tests\Fixtures\WebApp;

beforeEach(fn () => $this->app = WebApp::boot());
afterEach(fn () => WebApp::tearDown($this));

test('json resource null attributes', function () {
    $model = new class extends Model {
    };

    $model->setAttribute('relation_sum_column', null);
    $model->setAttribute('relation_count', null);
    $model->setAttribute('relation_exists', null);

    $resource = new JsonResource($model);

    expect($resource->whenAggregated('relation', 'column', 'sum'))->not->toBeInstanceOf(MissingValue::class);
    expect($resource->whenCounted('relation'))->not->toBeInstanceOf(MissingValue::class);
    expect($resource->whenExistsLoaded('relation'))->not->toBeInstanceOf(MissingValue::class);

    expect($resource->whenAggregated('relation', 'column', 'sum'))->toBeNull();
    expect($resource->whenCounted('relation'))->toBeNull();
    expect($resource->whenExistsLoaded('relation'))->toBeNull();
});

test('json resource to json succeeds with prior errors', function () {
    $this->app->registerInstance('request', Request::create('/'));

    $resource = new JsonResource(['foo' => 'bar']);

    // Simulate a JSON error
    json_decode('{');
    expect(json_last_error())->not->toBe(JSON_ERROR_NONE);

    expect($resource->toJson(JSON_THROW_ON_ERROR))->toBe('{"foo":"bar"}');
});

test('json resource to pretty print', function () {
    $resource = Mockery::mock(JsonResource::class)
        ->makePartial()
        ->expects('jsonSerialize')->times(3)->andReturn(['foo' => 'bar', 'bar' => 'foo', 'number' => 123])
        ->getMock();

    $results = $resource->toPrettyJson();
    $expected = $resource->toJson(JSON_PRETTY_PRINT);

    expect(json_decode($results, true))->toEqual(json_decode($expected, true));
    expect($results)->toBe($expected);
    expect($results)->toContain("\n");
    expect($results)->toContain('    ');

    $results = $resource->toPrettyJson(JSON_NUMERIC_CHECK);
    expect($results)->toContain("\n");
    expect($results)->toContain('    ');
    expect($results)->toContain('"number": 123');
});
