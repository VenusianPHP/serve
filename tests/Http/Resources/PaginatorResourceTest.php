<?php

use Mariner\Http\Request;
use Mariner\Http\Resources\Json\JsonResource;
use Venusian\Serve\Tests\Fixtures\Resources\Models\CursorResourceTestModel;
use Venusian\Serve\Tests\Fixtures\Resources\Models\PaginatorResourceTestModel;
use Venusian\Serve\Tests\Fixtures\Resources\TestJsonResource;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Pagination\CursorPaginator;
use Voyager\Pagination\LengthAwarePaginator;

// Laravel v13.37.0 tests/Pagination/{Paginator,Cursor}ResourceTest, through serve's paginator macros.

beforeEach(fn () => $this->app = WebApp::boot());
afterEach(fn () => WebApp::tearDown($this));

const PAGINATOR_RESOURCES = 'Venusian\\Serve\\Tests\\Fixtures\\Resources\\Http\\Resources\\';

test('a paginator can transform to an explicit resource', function () {
    $paginator = new LengthAwarePaginator([new PaginatorResourceTestModel], 1, 1, 1);

    expect($paginator->toResourceCollection(TestJsonResource::class))->toBeInstanceOf(JsonResource::class);
});

test('a paginator throws when its resource cannot be found', function () {
    (new LengthAwarePaginator([new PaginatorResourceTestModel], 1, 1, 1))->toResourceCollection();
})->throws(LogicException::class, 'Failed to find resource class for model ['.PaginatorResourceTestModel::class.'].');

test('a paginator guesses its resource when not given one', function () {
    class_alias(TestJsonResource::class, PAGINATOR_RESOURCES.'PaginatorResourceTestModelResource');

    expect((new LengthAwarePaginator([new PaginatorResourceTestModel], 1, 1, 1))->toResourceCollection())->toBeInstanceOf(JsonResource::class);
});

test('a cursor paginator can transform to an explicit resource', function () {
    expect((new CursorPaginator([new CursorResourceTestModel], 1))->toResourceCollection(TestJsonResource::class))->toBeInstanceOf(JsonResource::class);
});

test('a cursor paginator throws when its resource cannot be found', function () {
    (new CursorPaginator([new CursorResourceTestModel], 1))->toResourceCollection();
})->throws(LogicException::class, 'Failed to find resource class for model ['.CursorResourceTestModel::class.'].');

test('a cursor paginator guesses its resource when not given one', function () {
    class_alias(TestJsonResource::class, PAGINATOR_RESOURCES.'CursorResourceTestModelResource');

    expect((new CursorPaginator([new CursorResourceTestModel], 1))->toResourceCollection())->toBeInstanceOf(JsonResource::class);
});

test('a paginator\'s resource collection answers with its links and meta', function () {
    $paginator = new LengthAwarePaginator([new PaginatorResourceTestModel], 3, 1, 2, ['path' => 'http://localhost/items']);

    $json = json_decode($paginator->toResourceCollection(TestJsonResource::class)->toResponse(Request::create('/items?page=2'))->getContent(), true);

    expect($json)->toHaveKeys(['data', 'links', 'meta'])
        ->and($json['meta']['current_page'])->toBe(2)
        ->and($json['meta']['total'])->toBe(3);
});
