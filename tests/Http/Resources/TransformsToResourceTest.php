<?php

use Mariner\Http\Resources\Json\AnonymousResourceCollection;
use Mariner\Http\Resources\Json\JsonResource;
use Venusian\Serve\Tests\Fixtures\Resources\Models\PlainModel;
use Venusian\Serve\Tests\Fixtures\Resources\Models\ResourceCollectionTestModel;
use Venusian\Serve\Tests\Fixtures\Resources\Models\ResourceTestModel;
use Venusian\Serve\Tests\Fixtures\Resources\Models\ResourceTestModelWithGuessableResource;
use Venusian\Serve\Tests\Fixtures\Resources\Models\ResourceTestModelWithUseResource;
use Venusian\Serve\Tests\Fixtures\Resources\Models\ResourceTestModelWithUseResourceCollection;
use Venusian\Serve\Tests\Fixtures\Resources\TestJsonResource;
use Venusian\Serve\Tests\Fixtures\Resources\TestJsonResourceCollection;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Database\Instrument\Collection;

// Laravel v13.37.0 tests/Database/DatabaseEloquentResource{Model,Collection}Test, on models that opt in.

beforeEach(fn () => $this->app = WebApp::boot());
afterEach(fn () => WebApp::tearDown($this));

const RESOURCES = 'Venusian\\Serve\\Tests\\Fixtures\\Resources\\Http\\Resources\\';

test('a model can transform to an explicit resource', function () {
    $model = new ResourceTestModel;
    $resource = $model->toResource(TestJsonResource::class);

    expect($resource)->toBeInstanceOf(TestJsonResource::class)
        ->and($resource->resource)->toBe($model);
});

test('a model throws when its resource cannot be found', function () {
    (new ResourceTestModel)->toResource();
})->throws(LogicException::class, 'Failed to find resource class for model ['.ResourceTestModel::class.'].');

test('a model guesses its resource when not given one', function () {
    class_alias(TestJsonResource::class, RESOURCES.'ResourceTestModelWithGuessableResourceResource');
    $model = new ResourceTestModelWithGuessableResource;

    expect($model->toResource())->toBeInstanceOf(TestJsonResource::class)
        ->resource->toBe($model);
});

test('a model guesses its resource name', function () {
    expect(ResourceTestModel::guessResourceName())->toBe([
        RESOURCES.'ResourceTestModelResource',
        RESOURCES.'ResourceTestModel',
    ]);
});

test('a model transforms through #[UseResource]', function () {
    $model = new ResourceTestModelWithUseResource;

    expect($model->toResource())->toBeInstanceOf(TestJsonResource::class)
        ->resource->toBe($model);
});

test('a collection can transform to an explicit resource', function () {
    expect((new Collection([new ResourceCollectionTestModel]))->toResourceCollection(TestJsonResource::class))
        ->toBeInstanceOf(JsonResource::class);
});

test('a collection throws when its resource cannot be found', function () {
    (new Collection([new ResourceCollectionTestModel]))->toResourceCollection();
})->throws(LogicException::class, 'Failed to find resource class for model ['.ResourceCollectionTestModel::class.'].');

test('a collection guesses its resource when not given one', function () {
    class_alias(TestJsonResource::class, RESOURCES.'ResourceCollectionTestModelResource');

    expect((new Collection([new ResourceCollectionTestModel]))->toResourceCollection())->toBeInstanceOf(JsonResource::class);
});

test('a collection transforms through #[UseResourceCollection]', function () {
    expect((new Collection([new ResourceTestModelWithUseResourceCollection]))->toResourceCollection())
        ->toBeInstanceOf(TestJsonResourceCollection::class);
});

test('a collection transforms through the model\'s #[UseResource]', function () {
    $resource = (new Collection([new ResourceTestModelWithUseResource]))->toResourceCollection();

    expect($resource)->toBeInstanceOf(AnonymousResourceCollection::class)
        ->and($resource[0])->toBeInstanceOf(TestJsonResource::class);
});

test('an empty collection gives an empty resource collection', function () {
    expect((new Collection)->toResourceCollection())->toBeInstanceOf(Mariner\Http\Resources\Json\ResourceCollection::class)
        ->toHaveCount(0);
});

test('a collection of models that did not opt in says so', function () {
    (new Collection([new PlainModel]))->toResourceCollection();
})->throws(LogicException::class, 'Make sure the model uses the TransformsToResource trait.');
