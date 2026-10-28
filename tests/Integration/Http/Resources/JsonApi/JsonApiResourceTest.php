<?php

namespace Venusian\Serve\Tests\Integration\Http\Resources\JsonApi;

use Mariner\Http\Request;
use Mariner\Http\Resources\JsonApi\JsonApiResource;
use Mariner\NutsAndBolts\Facades\Route;
use Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures\ArrayBackedJsonApiResource;
use Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures\Comment;
use Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures\Post;
use Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures\PostResource;
use Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures\Profile;
use Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures\Team;
use Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures\User;
use Venusian\Serve\Tests\Integration\Http\Resources\JsonApi\Fixtures\JsonApiApp;
use Venusian\Serve\Tests\Integration\IntegrationTestCase;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeAll(fn () => IntegrationTestCase::withMigrations('laravel', 'json-api'));
afterAll(fn () => IntegrationTestCase::withMigrations());

beforeEach(fn () => JsonApiApp::prepare($this->app));
afterEach(fn () => Model::shouldBeStrict(false));


test('it can generate json api response with empty sparse fieldsets', function () {
    $user = User::factory()->create();

    $this->getJson("/users/{$user->getKey()}?".http_build_query(['fields' => ['users' => '']]))
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertExactJson([
            'data' => [
                'id' => (string) $user->getKey(),
                'type' => 'users',
            ],
        ])
        ->assertJsonMissing(['jsonapi', 'included']);
});

test('it can resolve relationship with custom name and resource class', function () {
    $now = $this->freezeSecond();

    $user = User::factory()->create();

    $profile = Profile::factory()->create([
        'user_id' => $user->getKey(),
        'date_of_birth' => '2011-06-09',
        'timezone' => 'America/Chicago',
    ]);

    [$post1, $post2] = Post::factory()->times(2)->create([
        'user_id' => $user->getKey(),
    ]);

    $comment = Comment::factory()->create([
        'post_id' => $post1->getKey(),
        'user_id' => $user->getKey(),
    ]);

    $this->getJson("/posts/{$post1->getKey()}?".http_build_query(['include' => 'author']))
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertExactJson([
            'data' => [
                'attributes' => [
                    'content' => $post1->content,
                    'title' => $post1->title,
                ],
                'type' => 'posts',
                'id' => (string) $post1->getKey(),
                'relationships' => [
                    'author' => [
                        'data' => [
                            'id' => (string) $user->getKey(),
                            'type' => 'authors',
                        ],
                    ],
                ],
            ],
            'included' => [
                [
                    'attributes' => [
                        'email' => $user->email,
                        'name' => $user->name,
                    ],
                    'id' => (string) $user->getKey(),
                    'type' => 'authors',
                ],
            ],
        ])
        ->assertJsonMissing(['jsonapi']);
});

test('it ignores nested includes that are not resource relationships', function () {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    Comment::factory()->create([
        'user_id' => $user->getKey(),
    ]);

    Comment::factory()->create([
        'user_id' => $otherUser->getKey(),
    ]);

    $this->getJson("/users/{$user->getKey()}?".http_build_query(['include' => 'comments.truncate']))
        ->assertOk()
        ->assertJsonCount(1, 'included');

    expect(Comment::count())->toBe(2);
});

test('it can resolve relationship with recursive nested relationship', function () {
    $now = $this->freezeSecond();

    $user = User::factory()->create();

    $profile = Profile::factory()->create([
        'user_id' => $user->getKey(),
        'date_of_birth' => '2011-06-09',
        'timezone' => 'America/Chicago',
    ]);

    $this->getJson("/users/{$user->getKey()}?".http_build_query(['include' => 'profile.user.profile']))
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertExactJson([
            'data' => [
                'attributes' => [
                    'email' => $user->email,
                    'name' => $user->name,
                ],
                'id' => (string) $user->getKey(),
                'type' => 'users',
                'relationships' => [
                    'profile' => [
                        'data' => ['id' => (string) $profile->getKey(), 'type' => 'profiles'],
                    ],
                ],
            ],
            'included' => [
                [
                    'attributes' => [
                        'date_of_birth' => '2011-06-09',
                        'timezone' => 'America/Chicago',
                    ],
                    'id' => (string) $profile->getKey(),
                    'type' => 'profiles',
                    'relationships' => [
                        'user' => [
                            'data' => ['id' => (string) $user->getKey(), 'type' => 'users'],
                        ],
                    ],
                ],
                [
                    'attributes' => [
                        'email' => $user->email,
                        'name' => $user->name,
                    ],
                    'id' => (string) $user->getKey(),
                    'type' => 'users',
                    'relationships' => [
                        'profile' => [
                            'data' => ['id' => (string) $profile->getKey(), 'type' => 'profiles'],
                        ],
                    ],
                ],
            ],
        ])
        ->assertJsonMissing(['jsonapi']);
});

test('it can resolve relationship with recursive nested relationship limited to depth configuration', function () {
    JsonApiResource::maxRelationshipDepth(2);

    $now = $this->freezeSecond();

    $user = User::factory()->create();

    $profile = Profile::factory()->create([
        'user_id' => $user->getKey(),
        'date_of_birth' => '2011-06-09',
        'timezone' => 'America/Chicago',
    ]);

    $this->getJson("/users/{$user->getKey()}?".http_build_query(['include' => 'profile.user.profile']))
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertExactJson([
            'data' => [
                'attributes' => [
                    'email' => $user->email,
                    'name' => $user->name,
                ],
                'id' => (string) $user->getKey(),
                'type' => 'users',
                'relationships' => [
                    'profile' => [
                        'data' => ['id' => (string) $profile->getKey(), 'type' => 'profiles'],
                    ],
                ],
            ],
            'included' => [
                [
                    'attributes' => [
                        'date_of_birth' => '2011-06-09',
                        'timezone' => 'America/Chicago',
                    ],
                    'id' => (string) $profile->getKey(),
                    'type' => 'profiles',
                    'relationships' => [
                        'user' => [
                            'data' => ['id' => (string) $user->getKey(), 'type' => 'users'],
                        ],
                    ],
                ],
                [
                    'attributes' => [
                        'email' => $user->email,
                        'name' => $user->name,
                    ],
                    'id' => (string) $user->getKey(),
                    'type' => 'users',
                ],
            ],
        ])
        ->assertJsonMissing(['jsonapi']);
});

test('it handles bidirectional relationships with chaperone without infinite loop', function () {
    $user = User::factory()->create();

    $post = Post::factory()->create([
        'user_id' => $user->getKey(),
    ]);

    // The /with-chaperone-posts route loads chaperonePosts which uses chaperone()
    // to automatically set the inverse 'author' relationship on each Post,
    // creating circular object references (User -> Post -> User same instance).
    // Without the fix, this would hang forever due to infinite loop.
    // The same User model appears twice in included: once as "posts" (the Post)
    // and once as "authors" (Post's author via chaperone) - this is correct
    // because different resource types should not be deduplicated.
    $this->getJson("/users/{$user->getKey()}/with-chaperone-posts?".http_build_query(['include' => 'chaperonePosts']))
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertJsonPath('data.id', (string) $user->getKey())
        ->assertJsonPath('data.type', 'users')
        ->assertJsonPath('data.relationships.chaperonePosts.data.0.type', 'posts')
        ->assertJsonPath('data.relationships.chaperonePosts.data.0.id', (string) $post->getKey())
        ->assertJsonPath('included.0.type', 'posts')
        ->assertJsonPath('included.1.type', 'authors')
        ->assertJsonCount(2, 'included');
});

test('included resources can be array backed custom resources', function () {
    $user = User::factory()->create();

    $this->getJson("/users/{$user->getKey()}/with-array-relationship")
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertJsonPath('data.id', (string) $user->getKey())
        ->assertJsonPath('data.type', 'users')
        ->assertJsonPath('included.0.id', '99')
        ->assertJsonPath('included.0.type', 'things')
        ->assertJsonPath('included.0.attributes.name', 'test')
        ->assertJsonCount(1, 'included');
});

test('top level array backed custom resource can generate json api response', function () {
    $this->getJson('/things/99')
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertExactJson([
            'data' => [
                'id' => '99',
                'type' => 'things',
                'attributes' => [
                    'name' => 'test',
                ],
            ],
        ])
        ->assertJsonMissing(['jsonapi', 'included']);
});

test('attributes can be conditionally merged', function () {
    Route::get('things-with-merged-attributes/{id}', function ($id) {
        return new class(['id' => (int) $id, 'name' => 'test', 'secret' => 'hidden', 'notes' => 'internal']) extends ArrayBackedJsonApiResource
        {
            public function toAttributes(Request $request)
            {
                return [
                    'name' => $this->resource['name'],
                    $this->mergeWhen(false, ['secret' => $this->resource['secret']]),
                    $this->mergeWhen(true, ['notes' => $this->resource['notes']]),
                ];
            }
        };
    });

    $this->getJson('/things-with-merged-attributes/99')
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertExactJson([
            'data' => [
                'id' => '99',
                'type' => 'things',
                'attributes' => [
                    'name' => 'test',
                    'notes' => 'internal',
                ],
            ],
        ]);
});

test('different model instances with same type and id are deduplicated', function () {
    $user = User::factory()->create();

    // This route manually creates two different User model instances with the same ID and
    // adds them both to the loadedRelationshipsMap. Per the JSON:API spec, they should
    // be deduplicated since they have the same type+id, even though they're different object instances.
    $response = $this->getJson("/users/{$user->getKey()}/with-duplicate-instances")
        ->assertHeader('Content-type', 'application/vnd.api+json');

    $included = $response->json('included');

    expect($included)->toHaveCount(1);
    expect($included[0]['type'])->toBe('users');
    expect($included[0]['id'])->toBe((string) $user->getKey());
});

test('same model on different resources is not deduplicated', function () {
    $user = User::factory()->create();

    $post = Post::factory()->create([
        'user_id' => $user->getKey(),
    ]);

    // Post's author uses AuthorResource ("authors"), root uses UserResource ("users").
    // We don't want to deduplicate them, as even though the underlying model is the
    // same, they are different resource types, so they have different identity.
    $this->getJson("/users/{$user->getKey()}/with-chaperone-posts?".http_build_query(['include' => 'chaperonePosts.author']))
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertJsonPath('data.id', (string) $user->getKey())
        ->assertJsonPath('data.type', 'users')
        ->assertJsonPath('included.0.type', 'posts')
        ->assertJsonPath('included.0.id', (string) $post->getKey())
        ->assertJsonPath('included.1.type', 'authors')
        ->assertJsonPath('included.1.id', (string) $user->getKey())
        ->assertJsonCount(2, 'included');
});

test('it can generate json api response', function () {
    $user = User::factory()->create();

    $this->getJson("/users/{$user->getKey()}")
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertExactJson([
            'data' => [
                'id' => (string) $user->getKey(),
                'type' => 'users',
                'attributes' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
            ],
        ])
        ->assertJsonMissing(['jsonapi', 'included']);
});

test('it can generate json api response with sparse fieldsets', function () {
    $user = User::factory()->create();

    $this->getJson("/users/{$user->getKey()}?".http_build_query(['fields' => ['users' => 'name']]))
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertExactJson([
            'data' => [
                'id' => (string) $user->getKey(),
                'type' => 'users',
                'attributes' => [
                    'name' => $user->name,
                ],
            ],
        ])
        ->assertJsonMissing(['jsonapi', 'included']);
});

test('it can generate json api response with empty relationships using sparse included', function () {
    $user = User::factory()->create();

    $this->getJson("/users/{$user->getKey()}?".http_build_query(['include' => 'posts']))
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertExactJson([
            'data' => [
                'id' => (string) $user->getKey(),
                'type' => 'users',
                'attributes' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'relationships' => [
                    'posts' => [
                        'data' => [],
                    ],
                ],
            ],
        ])
        ->assertJsonMissing(['jsonapi', 'included']);
});

test('it can generate json api response with relationships using sparse included', function () {
    $now = $this->freezeSecond();

    $user = User::factory()->create();

    $profile = Profile::factory()->create([
        'user_id' => $user->getKey(),
        'date_of_birth' => '2011-06-09',
        'timezone' => 'America/Chicago',
    ]);

    $team = Team::factory()->create([
        'name' => 'Laravel Team',
    ]);

    $user->teams()->attach($team, ['role' => 'Admin']);
    $user->teams()->attach($team, ['role' => 'Member']);

    $posts = Post::factory()->times(2)->create([
        'user_id' => $user->getKey(),
    ]);

    $this->getJson("/users/{$user->getKey()}?".http_build_query(['include' => 'profile,posts,teams']))
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertExactJson([
            'data' => [
                'id' => (string) $user->getKey(),
                'type' => 'users',
                'attributes' => [
                    'name' => $user->name,
                    'email' => $user->email,
                ],
                'relationships' => [
                    'posts' => [
                        'data' => [
                            ['id' => (string) $posts[0]->getKey(), 'type' => 'posts'],
                            ['id' => (string) $posts[1]->getKey(), 'type' => 'posts'],
                        ],
                    ],
                    'profile' => [
                        'data' => [
                            'id' => (string) $profile->getKey(),
                            'type' => 'profiles',
                        ],
                    ],
                    'teams' => [
                        'data' => [
                            ['id' => (string) $team->getKey(), 'type' => 'teams'],
                            ['id' => (string) $team->getKey(), 'type' => 'teams'],
                        ],
                    ],
                ],
            ],
            'included' => [
                [
                    'id' => (string) $profile->getKey(),
                    'type' => 'profiles',
                    'attributes' => [
                        'date_of_birth' => '2011-06-09',
                        'timezone' => 'America/Chicago',
                    ],
                ],
                [
                    'id' => (string) $posts[0]->getKey(),
                    'type' => 'posts',
                    'attributes' => [
                        'title' => $posts[0]->title,
                        'content' => $posts[0]->content,
                    ],
                ],
                [
                    'id' => (string) $posts[1]->getKey(),
                    'type' => 'posts',
                    'attributes' => [
                        'title' => $posts[1]->title,
                        'content' => $posts[1]->content,
                    ],
                ],
                [
                    'id' => (string) $team->getKey(),
                    'type' => 'teams',
                    'attributes' => [
                        'id' => $team->getKey(),
                        'user_id' => $team->user_id,
                        'name' => 'Laravel Team',
                        'personal_team' => true,
                        'membership' => [
                            'created_at' => $now->toISOString(),
                            'role' => 'Admin',
                            'team_id' => $team->getKey(),
                            'user_id' => $user->getKey(),
                            'updated_at' => $now->toISOString(),
                        ],
                    ],
                ],
                [
                    'id' => (string) $team->getKey(),
                    'type' => 'teams',
                    'attributes' => [
                        'id' => $team->getKey(),
                        'user_id' => $team->user_id,
                        'name' => 'Laravel Team',
                        'personal_team' => true,
                        'membership' => [
                            'created_at' => $now->toISOString(),
                            'role' => 'Member',
                            'team_id' => $team->getKey(),
                            'user_id' => $user->getKey(),
                            'updated_at' => $now->toISOString(),
                        ],
                    ],
                ],
            ],
        ]);
});

test('it can generate json api response with relationships using sparse included and sparse fieldsets', function () {
    $now = $this->freezeSecond();

    $user = User::factory()->create();

    [$post1, $post2] = Post::factory()->times(2)->create([
        'user_id' => $user->getKey(),
    ]);

    $this->getJson('/posts?'.http_build_query(['include' => 'author', 'fields' => ['authors' => 'name']]))
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertExactJson([
            'data' => [
                [
                    'attributes' => [
                        'content' => $post1->content,
                        'title' => $post1->title,
                    ],
                    'type' => 'posts',
                    'id' => (string) $post1->getKey(),
                    'relationships' => [
                        'author' => [
                            'data' => [
                                'id' => (string) $user->getKey(),
                                'type' => 'authors',
                            ],
                        ],
                    ],
                ],
                [
                    'attributes' => [
                        'content' => $post2->content,
                        'title' => $post2->title,
                    ],
                    'type' => 'posts',
                    'id' => (string) $post2->getKey(),
                    'relationships' => [
                        'author' => [
                            'data' => [
                                'id' => (string) $user->getKey(),
                                'type' => 'authors',
                            ],
                        ],
                    ],
                ],
            ],
            'included' => [
                [
                    'attributes' => [
                        'name' => $user->name,
                    ],
                    'id' => (string) $user->getKey(),
                    'type' => 'authors',
                ],
            ],
            'links' => [
                'first' => url('/posts?page=1'),
                'last' => url('/posts?page=1'),
                'next' => null,
                'prev' => null,
            ],
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 1,
                'links' => [
                    ['active' => false, 'label' => '&laquo; Previous', 'page' => null, 'url' => null],
                    ['active' => true, 'label' => '1', 'page' => 1, 'url' => url('/posts?page=1')],
                    ['active' => false, 'label' => 'Next &raquo;', 'page' => null, 'url' => null],
                ],
                'path' => url('/posts'),
                'per_page' => 5,
                'to' => 2,
                'total' => 2,
            ],
        ])
        ->assertJsonCount(1, 'included')
        ->assertJsonMissing(['jsonapi']);
});

test('it can resolve relationship with nested relationship', function () {
    $now = $this->freezeSecond();

    $user = User::factory()->create();

    $profile = Profile::factory()->create([
        'user_id' => $user->getKey(),
        'date_of_birth' => '2011-06-09',
        'timezone' => 'America/Chicago',
    ]);

    [$post1, $post2] = Post::factory()->times(2)->create([
        'user_id' => $user->getKey(),
    ]);

    $comment = Comment::factory()->create([
        'post_id' => $post1->getKey(),
        'user_id' => $user->getKey(),
    ]);

    $this->getJson("/posts/{$post1->getKey()}?".http_build_query(['include' => 'author,comments.commenter']))
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertExactJson([
            'data' => [
                'attributes' => [
                    'content' => $post1->content,
                    'title' => $post1->title,
                ],
                'type' => 'posts',
                'id' => (string) $post1->getKey(),
                'relationships' => [
                    'author' => [
                        'data' => [
                            'id' => (string) $user->getKey(),
                            'type' => 'authors',
                        ],
                    ],
                    'comments' => [
                        'data' => [
                            ['id' => (string) $comment->getKey(), 'type' => 'comments'],
                        ],
                    ],
                ],
            ],
            'included' => [
                [
                    'attributes' => [
                        'email' => $user->email,
                        'name' => $user->name,
                    ],
                    'id' => (string) $user->getKey(),
                    'type' => 'authors',
                ],
                [
                    'attributes' => [
                        'content' => $comment->content,
                    ],
                    'id' => (string) $comment->getKey(),
                    'type' => 'comments',
                    'relationships' => [
                        'commenter' => [
                            'data' => [
                                'id' => (string) $user->getKey(),
                                'type' => 'users',
                            ],
                        ],
                    ],
                ],
                [
                    'attributes' => [
                        'email' => $user->email,
                        'name' => $user->name,
                    ],
                    'id' => (string) $user->getKey(),
                    'type' => 'users',
                ],
            ],
        ])
        ->assertJsonMissing(['jsonapi']);
});

test('it can resolve nested relationship through closure returning resource collection', function () {
    $user = User::factory()->create();

    $publicPost = Post::factory()->create([
        'user_id' => $user->getKey(),
    ]);

    $privatePost = Post::factory()->create([
        'user_id' => $user->getKey(),
    ]);

    $publicComment = Comment::factory()->create([
        'content' => 'public',
        'post_id' => $publicPost->getKey(),
        'user_id' => $user->getKey(),
    ]);

    $privateComment = Comment::factory()->create([
        'content' => 'private',
        'post_id' => $privatePost->getKey(),
        'user_id' => $user->getKey(),
    ]);

    $response = $this->getJson("/users/{$user->getKey()}?".http_build_query(['include' => 'posts.comments']))
        ->assertJsonPath('data.relationships.posts.data.0.id', (string) $publicPost->getKey())
        ->assertJsonPath('included.0.relationships.comments.data.0.id', (string) $publicComment->getKey());

    expect(collect($response->json('included'))->contains(
        fn ($included) => $included['type'] === 'comments' && $included['id'] === (string) $privateComment->getKey()
    ))->toBeFalse();
});

test('it ignores deeply nested includes that are not resource relationships', function () {
    $user = User::factory()->create();
    $post = Post::factory()->create([
        'user_id' => $user->getKey(),
    ]);

    Comment::factory()->create([
        'post_id' => $post->getKey(),
        'user_id' => $user->getKey(),
    ]);

    Comment::factory()->create();

    $this->getJson("/users/{$user->getKey()}?".http_build_query(['include' => 'posts.comments.truncate']))
        ->assertOk()
        ->assertJsonCount(2, 'included');

    expect(Comment::count())->toBe(2);
});

test('it eager loads nested includes for all related models at once', function () {
    $user = User::factory()->create();

    $posts = Post::factory()->count(3)->create([
        'user_id' => $user->getKey(),
    ]);

    foreach ($posts as $post) {
        Comment::factory()->create([
            'post_id' => $post->getKey(),
            'user_id' => $user->getKey(),
        ]);
    }

    $connection = $this->app['db']->connection();
    $connection->enableQueryLog();

    $this->getJson("/users/{$user->getKey()}?".http_build_query(['include' => 'posts.comments']))
        ->assertOk()
        ->assertJsonCount(6, 'included');

    // One query for the user, one for the posts and one for the comments of all posts...
    expect($connection->getQueryLog())->toHaveCount(3);
});

test('it resolves each relationship closure once when including nested relationships', function () {
    $user = User::factory()->create();

    $posts = Post::factory()->count(2)->create([
        'user_id' => $user->getKey(),
    ]);

    foreach ($posts as $post) {
        Comment::factory()->create([
            'content' => 'public',
            'post_id' => $post->getKey(),
            'user_id' => $user->getKey(),
        ]);
    }

    PostResource::$commentsResolutionCount = 0;

    $this->getJson("/users/{$user->getKey()}?".http_build_query(['include' => 'posts.comments']))
        ->assertJsonPath('data.relationships.posts.data.0.id', (string) $posts[0]->getKey());

    // The "comments" closure should be resolved once per included post, not multiple times...
    expect(PostResource::$commentsResolutionCount)->toBe(2);
});

test('it can resolve relationship without redundant included relationship', function () {
    $now = $this->freezeSecond();

    $user = User::factory()->create();

    [$post1, $post2] = Post::factory()->times(2)->create([
        'user_id' => $user->getKey(),
    ]);

    $this->getJson('/posts?'.http_build_query(['include' => 'author']))
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertExactJson([
            'data' => [
                [
                    'attributes' => [
                        'content' => $post1->content,
                        'title' => $post1->title,
                    ],
                    'type' => 'posts',
                    'id' => (string) $post1->getKey(),
                    'relationships' => [
                        'author' => [
                            'data' => [
                                'id' => (string) $user->getKey(),
                                'type' => 'authors',
                            ],
                        ],
                    ],
                ],
                [
                    'attributes' => [
                        'content' => $post2->content,
                        'title' => $post2->title,
                    ],
                    'type' => 'posts',
                    'id' => (string) $post2->getKey(),
                    'relationships' => [
                        'author' => [
                            'data' => [
                                'id' => (string) $user->getKey(),
                                'type' => 'authors',
                            ],
                        ],
                    ],
                ],
            ],
            'included' => [
                [
                    'attributes' => [
                        'email' => $user->email,
                        'name' => $user->name,
                    ],
                    'id' => (string) $user->getKey(),
                    'type' => 'authors',
                ],
            ],
            'links' => [
                'first' => url('/posts?page=1'),
                'last' => url('/posts?page=1'),
                'next' => null,
                'prev' => null,
            ],
            'meta' => [
                'current_page' => 1,
                'from' => 1,
                'last_page' => 1,
                'links' => [
                    ['active' => false, 'label' => '&laquo; Previous', 'page' => null, 'url' => null],
                    ['active' => true, 'label' => '1', 'page' => 1, 'url' => url('/posts?page=1')],
                    ['active' => false, 'label' => 'Next &raquo;', 'page' => null, 'url' => null],
                ],
                'path' => url('/posts'),
                'per_page' => 5,
                'to' => 2,
                'total' => 2,
            ],
        ])
        ->assertJsonCount(1, 'included')
        ->assertJsonMissing(['jsonapi']);
});

test('same model with the same resource type is deduplicated', function () {
    $user = User::factory()->create();

    $post1 = Post::factory()->create([
        'user_id' => $user->getKey(),
    ]);

    $post2 = Post::factory()->create([
        'user_id' => $user->getKey(),
    ]);

    Comment::factory()->create([
        'post_id' => $post1->getKey(),
        'user_id' => $user->getKey(),
    ]);

    Comment::factory()->create([
        'post_id' => $post2->getKey(),
        'user_id' => $user->getKey(),
    ]);

    // Both comments have the same commenter (User). Per the JSON:API spec, the same
    // type+id pair should only appear once in included, even when referenced by
    // multiple relationships. We expect 3 items: 2 comments + 1 user (deduped).
    $response = $this->getJson('/posts?'.http_build_query(['include' => 'comments.commenter']))
        ->assertHeader('Content-type', 'application/vnd.api+json')
        ->assertJsonCount(3, 'included');

    $included = $response->json('included');
    $types = array_column($included, 'type');

    expect(array_filter($types, fn (string $t) => $t === 'comments'))->toHaveCount(2);
    expect(array_filter($types, fn (string $t) => $t === 'users'))->toHaveCount(1);
});

