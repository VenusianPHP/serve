<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\Routing\RouteCache;
use Venusian\Serve\Tests\Integration\Fixtures\UserFactory;
use Venusian\Serve\Tests\Integration\IntegrationTestCase;
use Voyager\Database\Testing\RefreshDatabase;

use function Voyager\Filesystem\join_paths;

uses(RefreshDatabase::class);

beforeAll(fn () => IntegrationTestCase::withMigrations('laravel'));
afterAll(fn () => IntegrationTestCase::withMigrations());

// A routes-v7.php written by Laravel's route:cache, with closures serialized by laravel/serializable-closure v1.
beforeEach(function () {
    $_ENV['APP_ROUTES_CACHE'] = realpath(join_paths(dirname(__DIR__, 2), 'Routing', 'Fixtures', 'serializable-closure-v1', 'routes-v7.php'));

    $this->rebootApplication();
    $this->app['config']->set('app.key', 'AckfSECXIvnK5r28GVIWUAxmbBSjTsmF');
    $this->app['view']->addLocation(__DIR__.'/Fixtures/views');
});

afterEach(function () {
    unset($_ENV['APP_ROUTES_CACHE']);
});

test('it can use cached route from serializable closure v1', function () {
    $user = UserFactory::new()->create();

    expect($this->app->make(RouteCache::class)->isCached())->toBeTrue();

    $this->get('/')->assertSee('Laravel');

    $this->get("/users/{$user->getKey()}")
        ->assertJson($user->toArray());
});
