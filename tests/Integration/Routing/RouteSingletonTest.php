<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\NutsAndBolts\Facades\Route;
use Venusian\Serve\Tests\Routing\Fixtures\CreatableSingletonTestController;
use Venusian\Serve\Tests\Routing\Fixtures\NestedSingletonTestController;
use Venusian\Serve\Tests\Routing\Fixtures\SingletonTestController;

test('singleton defaults', function () {
    Route::singleton('avatar', SingletonTestController::class);

    $response = $this->get('/avatar/create');
    expect($response->getStatusCode())->toEqual(404);

    expect(route('avatar.show'))->toBe('http://localhost/avatar');
    $response = $this->get('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton show');

    expect(route('avatar.edit'))->toBe('http://localhost/avatar/edit');
    $response = $this->get('/avatar/edit');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton edit');

    expect(route('avatar.update'))->toBe('http://localhost/avatar');
    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton update');

    $response = $this->patch('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton update');

    // $this->assertSame('http://localhost/avatar', route('avatar.destroy'));
    // $response = $this->delete('/avatar');
    // $this->assertEquals(404, $response->getStatusCode());
    // $this->assertSame('singleton destroy', $response->getContent());
});

test('creatable singleton', function () {
    Route::singleton('avatar', CreatableSingletonTestController::class)->creatable();

    expect(route('avatar.create'))->toBe('http://localhost/avatar/create');
    $response = $this->get('/avatar/create');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton create');

    expect(route('avatar.store'))->toBe('http://localhost/avatar');
    $response = $this->post('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton store');

    expect(route('avatar.destroy'))->toBe('http://localhost/avatar');
    $response = $this->delete('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton destroy');
});

test('creatable singleton only', function () {
    Route::singleton('avatar', CreatableSingletonTestController::class)->creatable()->only('show');

    $response = $this->get('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->get('/avatar/create');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->post('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->get('/avatar/edit');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->patch('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->delete('/avatar');
    expect($response->getStatusCode())->toEqual(405);
});

test('creatable singleton except', function () {
    Route::singleton('avatar', CreatableSingletonTestController::class)->creatable()->except('show');

    $response = $this->get('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->get('/avatar/create');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->post('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->get('/avatar/edit');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->patch('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->delete('/avatar');
    expect($response->getStatusCode())->toEqual(200);
});

test('destroyable singleton', function () {
    Route::singleton('avatar', CreatableSingletonTestController::class)->destroyable();

    expect(route('avatar.show'))->toBe('http://localhost/avatar');
    $response = $this->get('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton show');

    expect(route('avatar.edit'))->toBe('http://localhost/avatar/edit');
    $response = $this->get('/avatar/edit');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton edit');

    expect(route('avatar.update'))->toBe('http://localhost/avatar');
    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton update');

    expect(route('avatar.destroy'))->toBe('http://localhost/avatar');
    $response = $this->delete('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton destroy');
});

test('destroyable singleton only', function () {
    Route::singleton('avatar', SingletonTestController::class)->destroyable()->only('destroy');

    $response = $this->get('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->get('/avatar/create');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->post('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->get('/avatar/edit');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->patch('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->delete('/avatar');
    expect($response->getStatusCode())->toEqual(200);
});

test('destroyable singleton except', function () {
    Route::singleton('avatar', SingletonTestController::class)->destroyable()->except('destroy');

    $response = $this->get('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->get('/avatar/create');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->post('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->get('/avatar/edit');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->patch('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->delete('/avatar');
    expect($response->getStatusCode())->toEqual(405);
});

test('creatable destroyable singleton only except test', function () {
    Route::singleton('avatar', SingletonTestController::class)->creatable()->destroyable()->only(['show'])->except(['destroy']);

    $response = $this->get('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->get('/avatar/create');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->post('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->get('/avatar/edit');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->patch('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->delete('/avatar');
    expect($response->getStatusCode())->toEqual(405);
});

test('api singleton', function () {
    Route::apiSingleton('avatar', SingletonTestController::class);

    $response = $this->get('/avatar/create');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->post('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    expect(route('avatar.update'))->toBe('http://localhost/avatar');
    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton update');
});

test('creatable api singleton', function () {
    Route::apiSingleton('avatar', CreatableSingletonTestController::class)->creatable();

    $response = $this->get('/avatar/create');
    expect($response->getStatusCode())->toEqual(404);

    expect(route('avatar.store'))->toBe('http://localhost/avatar');
    $response = $this->post('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton store');

    expect(route('avatar.update'))->toBe('http://localhost/avatar');
    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton update');
});

test('creatable api singleton only', function () {
    Route::apiSingleton('avatar', CreatableSingletonTestController::class)->creatable()->only(['create', 'store']);

    $response = $this->get('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->get('/avatar/create');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->post('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->get('/avatar/edit');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->patch('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->delete('/avatar');
    expect($response->getStatusCode())->toEqual(405);
});

test('creatable api singleton except', function () {
    Route::apiSingleton('avatar', CreatableSingletonTestController::class)->creatable()->except(['create', 'store']);

    $response = $this->get('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->get('/avatar/create');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->post('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->get('/avatar/edit');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->patch('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->delete('/avatar');
    expect($response->getStatusCode())->toEqual(200);
});

test('destroyable api singleton', function () {
    Route::apiSingleton('avatar', CreatableSingletonTestController::class)->destroyable();

    expect(route('avatar.show'))->toBe('http://localhost/avatar');
    $response = $this->get('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton show');

    expect(route('avatar.update'))->toBe('http://localhost/avatar');
    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton update');

    expect(route('avatar.destroy'))->toBe('http://localhost/avatar');
    $response = $this->delete('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton destroy');
});

test('destroyable api singleton only', function () {
    Route::apiSingleton('avatar', CreatableSingletonTestController::class)->destroyable()->only(['destroy']);

    $response = $this->get('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->get('/avatar/create');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->post('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->get('/avatar/edit');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->patch('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->delete('/avatar');
    expect($response->getStatusCode())->toEqual(200);
});

test('destroyable api singleton except', function () {
    Route::apiSingleton('avatar', CreatableSingletonTestController::class)->destroyable()->except(['destroy', 'show']);

    $response = $this->get('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->get('/avatar/create');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->post('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->get('/avatar/edit');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->patch('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->delete('/avatar');
    expect($response->getStatusCode())->toEqual(405);
});

test('creatable destroyable api singleton only except test', function () {
    Route::apiSingleton('avatar', CreatableSingletonTestController::class)->creatable()->destroyable()->only(['show'])->except(['destroy']);

    $response = $this->get('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->get('/avatar/create');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->post('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->get('/avatar/edit');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->patch('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->delete('/avatar');
    expect($response->getStatusCode())->toEqual(405);
});

test('singleton only', function () {
    Route::singleton('avatar', SingletonTestController::class)->only('show');

    $response = $this->get('/avatar');
    expect($response->getStatusCode())->toEqual(200);

    $response = $this->get('/avatar/edit');
    expect($response->getStatusCode())->toEqual(404);

    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->patch('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->delete('/avatar');
    expect($response->getStatusCode())->toEqual(405);
});

test('singleton except', function () {
    Route::singleton('avatar', SingletonTestController::class)->except('show');

    $response = $this->get('/avatar');
    expect($response->getStatusCode())->toEqual(405);

    $response = $this->get('/avatar/edit');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton edit');

    $response = $this->put('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton update');

    $response = $this->patch('/avatar');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton update');

    // $response = $this->delete('/avatar');
    // $this->assertEquals(200, $response->getStatusCode());
    // $this->assertSame('singleton destroy', $response->getContent());
});

test('singleton name', function () {
    Route::singleton('avatar', SingletonTestController::class)->name('show', 'foo.show');

    expect(route('foo.show'))->toBe('http://localhost/avatar');
});

test('singleton names', function () {
    Route::singleton('avatar', SingletonTestController::class)->names(['show' => 'foo.show']);

    expect(route('foo.show'))->toBe('http://localhost/avatar');
});

test('nested singleton', function () {
    Route::singleton('videos.thumbnail', NestedSingletonTestController::class);

    $response = $this->get('/videos/123/thumbnail');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton show for 123');

    $response = $this->get('/videos/123/thumbnail/edit');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton edit for 123');

    $response = $this->put('/videos/123/thumbnail');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton update for 123');

    $response = $this->patch('/videos/123/thumbnail');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton update for 123');

    $response = $this->delete('/videos/123/thumbnail');
    expect($response->getStatusCode())->toEqual(405);
});

test('creatable nested singleton', function () {
    Route::singleton('videos.thumbnail', NestedSingletonTestController::class)->creatable();

    $response = $this->get('/videos/123/thumbnail');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton show for 123');

    $response = $this->get('/videos/123/thumbnail/edit');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton edit for 123');

    $response = $this->put('/videos/123/thumbnail');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton update for 123');

    $response = $this->patch('/videos/123/thumbnail');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton update for 123');

    $response = $this->delete('/videos/123/thumbnail');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton destroy for 123');
});

test('destroyable nested singleton', function () {
    Route::singleton('videos.thumbnail', NestedSingletonTestController::class)->destroyable();

    $response = $this->get('/videos/123/thumbnail');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton show for 123');

    $response = $this->get('/videos/123/thumbnail/edit');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton edit for 123');

    $response = $this->put('/videos/123/thumbnail');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton update for 123');

    $response = $this->patch('/videos/123/thumbnail');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton update for 123');

    $response = $this->delete('/videos/123/thumbnail');
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton destroy for 123');
});

test('nested singleton parameter', function () {
    Route::singleton('v.thumbnail', NestedSingletonTestController::class)->parameter('v', 'video');

    $response = $this->get('/v/123/thumbnail');

    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton show for 123');
});

test('nested singleton parameters', function () {
    Route::singleton('v.thumbnail', NestedSingletonTestController::class)->parameters(['v' => 'video']);

    $response = $this->get('/v/123/thumbnail');

    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBe('singleton show for 123');
});

test('nested singleton where', function () {
    Route::singleton('videos.thumbnail', NestedSingletonTestController::class)->where(['video' => '[a-z]+']);

    $response = $this->get('/videos/123/thumbnail');

    expect($response->getStatusCode())->toEqual(404);
});

test('prefixed singleton', function () {
    Route::singleton('/user/avatar', SingletonTestController::class);

    $response = $this->get('/user/avatar');

    expect($response->getStatusCode())->toEqual(200);
});
