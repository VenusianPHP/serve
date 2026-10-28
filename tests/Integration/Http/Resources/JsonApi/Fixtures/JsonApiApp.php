<?php

namespace Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures;

use Voyager\Database\Instrument\Model;

/** What the JSON:API resource tests' Testbench base case set up: strict models, the user model, and the routes the tests request. */
class JsonApiApp
{
    public static function prepare($app): void
    {
        Model::shouldBeStrict(true);

        $app['config']->set('auth.providers.users.model', User::class);

        $router = $app['router'];

        $router->get('users', function () {
            return User::paginate(5)->toResourceCollection();
        });

        $router->get('users/{userId}', function ($userId) {
            return User::find($userId)->toResource();
        });

        $router->get('users/{userId}/with-chaperone-posts', function ($userId) {
            return User::find($userId)->load('chaperonePosts')->toResource();
        });

        $router->get('posts', function () {
            return Post::paginate(5)->toResourceCollection();
        });

        $router->get('posts/{postId}', function ($postId) {
            return Post::find($postId)->toResource();
        });

        $router->get('things/{id}', function ($id) {
            return new ArrayBackedJsonApiResource(['id' => (int) $id, 'name' => 'test']);
        });

        $router->get('users/{userId}/with-array-relationship', function ($userId) {
            $resource = new UserWithArrayRelationshipResource(User::find($userId));
            $resource->loadedRelationshipsMap = [
                [new ArrayBackedJsonApiResource(['id' => 99, 'name' => 'test']), 'things', '99', true],
            ];

            return $resource;
        });

        $router->get('users/{userId}/with-duplicate-instances', function ($userId) {
            $instance1 = User::find($userId);
            $instance2 = User::find($userId);

            $resource = new UserWithArrayRelationshipResource(User::find($userId));
            $resource->loadedRelationshipsMap = [
                [new UserResource($instance1), 'users', (string) $instance1->getKey(), true],
                [new UserResource($instance2), 'users', (string) $instance2->getKey(), true],
            ];

            return $resource;
        });
    }
}
