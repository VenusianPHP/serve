<?php

namespace Venusian\Serve\Tests\Http\Resources\JsonApi;

use Voyager\Database\Instrument\Collection as InstrumentCollection;
use Voyager\Database\Instrument\Model;
use Mariner\Http\Resources\JsonApi\JsonApiResource;
use Mariner\Http\Resources\JsonApi\RelationResolver;

test('resolves closure returning resource collection', function () {
    $first = new RelationResolverTestModel(['id' => 1]);
    $second = new RelationResolverTestModel(['id' => 2]);

    $resolver = new RelationResolver('comments', fn () => RelationResolverTestResource::collection([$first, $second]));

    $resolved = $resolver->handle(new RelationResolverTestModel);

    expect($resolved)->toBeInstanceOf(InstrumentCollection::class);
    expect($resolved->all())->toBe([$first, $second]);
    expect($resolver->resourceClass())->toBe(RelationResolverTestResource::class);
});

test('resolves closure returning single resource', function () {
    $model = new RelationResolverTestModel(['id' => 1]);

    $resolver = new RelationResolver('author', fn () => new RelationResolverTestResource($model));

    $resolved = $resolver->handle(new RelationResolverTestModel);

    expect($resolved)->toBe($model);
    expect($resolver->resourceClass())->toBe(RelationResolverTestResource::class);
});

test('resolves closure returning raw models', function () {
    $model = new RelationResolverTestModel(['id' => 1]);

    $resolver = new RelationResolver('comments', fn () => new InstrumentCollection([$model]));

    $resolved = $resolver->handle(new RelationResolverTestModel);

    expect($resolved)->toBeInstanceOf(InstrumentCollection::class);
    expect($resolved->all())->toBe([$model]);
    expect($resolver->resourceClass())->toBeNull();
});

test('resolves closure returning null', function () {
    $resolver = new RelationResolver('author', fn () => null);

    expect($resolver->handle(new RelationResolverTestModel))->toBeNull();
    expect($resolver->resourceClass())->toBeNull();
});

class RelationResolverTestModel extends Model
{
    protected $guarded = [];
}

class RelationResolverTestResource extends JsonApiResource
{
    //
}
