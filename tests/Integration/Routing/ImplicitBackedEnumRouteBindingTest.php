<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\NutsAndBolts\Facades\Route;
use Venusian\Serve\Tests\Routing\Fixtures\Integration\CategoryBackedEnum;

beforeEach(function () {
    $app = $this->app;
    $app['config']->set(['app.key' => 'AckfSECXIvnK5r28GVIWUAxmbBSjTsmF']);
});

test('with route caching enabled', function () {
    $this->defineCacheRoutes(<<<PHP
<?php

use Mariner\NutsAndBolts\Facades\Route;
use Venusian\Serve\Tests\Routing\Fixtures\Integration\CategoryBackedEnum;

Route::get('/categories/{category}', function (CategoryBackedEnum \$category) {
return \$category->value;
})->middleware('web');

Route::get('/categories-default/{category?}', function (CategoryBackedEnum \$category = CategoryBackedEnum::Fruits) {
return \$category->value;
})->middleware('web');
PHP);

    $response = $this->get('/categories/fruits');
    $response->assertSee('fruits');

    $response = $this->get('/categories/people');
    $response->assertSee('people');

    $response = $this->get('/categories/cars');
    $response->assertNotFound(404);

    $response = $this->get('/categories-default/');
    $response->assertSee('fruits');

    $response = $this->get('/categories-default/people');
    $response->assertSee('people');

    $response = $this->get('/categories-default/fruits');
    $response->assertSee('fruits');
});

test('without route caching enabled', function () {
    config(['app.key' => str_repeat('a', 32)]);

    Route::post('/categories/{category}', function (CategoryBackedEnum $category) {
        return $category->value;
    })->middleware(['web']);

    Route::post('/categories-default/{category?}', function (CategoryBackedEnum $category = CategoryBackedEnum::Fruits) {
        return $category->value;
    })->middleware('web');

    Route::bind('categoryCode', fn (string $categoryCode) => CategoryBackedEnum::fromCode($categoryCode) ?? abort(404));

    Route::post('/categories-code/{categoryCode}', function (CategoryBackedEnum $categoryCode) {
        return $categoryCode->value;
    })->middleware(['web']);

    $response = $this->post('/categories/fruits');
    $response->assertSee('fruits');

    $response = $this->post('/categories/people');
    $response->assertSee('people');

    $response = $this->post('/categories/cars');
    $response->assertNotFound();

    $response = $this->post('/categories-default/');
    $response->assertSee('fruits');

    $response = $this->post('/categories-default/people');
    $response->assertSee('people');

    $response = $this->post('/categories-default/fruits');
    $response->assertSee('fruits');

    $response = $this->post('/categories-code/c01');
    $response->assertSee('people');

    $response = $this->post('/categories-code/c02');
    $response->assertSee('fruits');

    $response = $this->post('/categories-code/00');
    $response->assertNotFound();
});
