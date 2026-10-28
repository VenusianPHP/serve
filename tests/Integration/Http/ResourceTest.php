<?php

namespace Venusian\Serve\Tests\Integration\Http;

use Voyager\Database\Instrument\Model;
use Mariner\Auth\User;
use Mariner\Http\Middleware\ValidatePostSize;
use Mariner\Http\Exceptions\PostTooLargeException;
use Mariner\Http\Request;
use Mariner\Http\Resources\ConditionallyLoadsAttributes;
use Mariner\Http\Resources\Json\JsonResource;
use Mariner\Http\Resources\Json\ResourceCollection;
use Mariner\Http\Resources\JsonApi\AnonymousResourceCollection;
use Mariner\Http\Resources\MergeValue;
use Mariner\Http\Resources\MissingValue;
use Voyager\Pagination\Cursor;
use Voyager\Pagination\CursorPaginator;
use Voyager\Pagination\LengthAwarePaginator;
use Voyager\NutsAndBolts\Collection;
use Mariner\NutsAndBolts\Facades\Route;
use Venusian\Serve\Tests\Integration\Http\Fixtures\Author;
use Venusian\Serve\Tests\Integration\Http\Fixtures\AuthorResourceWithOptionalRelationship;
use Venusian\Serve\Tests\Integration\Http\Fixtures\EmptyPostCollectionResource;
use Venusian\Serve\Tests\Integration\Http\Fixtures\ObjectResource;
use Venusian\Serve\Tests\Integration\Http\Fixtures\Post;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostCollectionResource;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostCollectionResourceWithPaginationInformation;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostModelCollectionResource;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResource;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithAnonymousResourceCollectionWithPaginationInformation;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithExtraData;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithJsonOptions;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithJsonOptionsAndTypeHints;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithOptionalAppendedAttributes;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithOptionalAttributes;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithOptionalData;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithOptionalHasAttributes;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithOptionalMerging;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithOptionalPivotRelationship;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithOptionalRelationship;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithOptionalRelationshipAggregates;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithOptionalRelationshipCounts;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithOptionalRelationshipExists;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithOptionalRelationshipUsingNamedParameters;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithoutWrap;
use Venusian\Serve\Tests\Integration\Http\Fixtures\PostResourceWithUnlessOptionalData;
use Venusian\Serve\Tests\Integration\Http\Fixtures\ReallyEmptyPostResource;
use Venusian\Serve\Tests\Integration\Http\Fixtures\ResourceWithPreservedKeys;
use Venusian\Serve\Tests\Integration\Http\Fixtures\SerializablePostResource;
use Venusian\Serve\Tests\Integration\Http\Fixtures\Subscription;
use LogicException;

test('resource may be conveted to array', function () {
    $resource = new class((new User)->forceFill(['id' => 1, 'name' => 'Taylor Otwell'])) extends JsonResource
    {
        public function toArray(Request $request)
        {
            return [
                'id' => $this->id,
                'name' => $this->name,
                'posts' => (new AnonymousResourceCollection([
                    new Post([
                        'id' => 5,
                        'title' => 'Test Title',
                        'abstract' => 'Test abstract',
                    ]),
                    new Post([
                        'id' => 10,
                        'title' => 'Another Test Title',
                        'abstract' => 'Another Test abstract',
                    ]),
                ], PostResource::class)),
            ];
        }
    };

    $request = Request::create('GET', '/users');

    tap($resource->toArray($request), function ($userAsArray) use ($request) {
        expect($userAsArray['id'])->toBe(1);
        expect($userAsArray['name'])->toBe('Taylor Otwell');

        expect($userAsArray['posts'])->toBeInstanceOf(AnonymousResourceCollection::class);
        expect($userAsArray['posts']->collects)->toBe(PostResource::class);

        tap($userAsArray['posts']->toArray($request), function ($postsAsArray) {
            expect($postsAsArray)->toBeArray();
            expect($postsAsArray)->toHaveCount(2);
            expect($postsAsArray[0])->toBe(['id' => 5, 'title' => 'Test Title', 'custom' => true]);
            expect($postsAsArray[1])->toBe(['id' => 10, 'title' => 'Another Test Title', 'custom' => true]);
        });
    });
});

test('resources may be converted to json', function () {
    Route::get('/', function () {
        return new PostResource(new Post([
            'id' => 5,
            'title' => 'Test Title',
            'abstract' => 'Test abstract',
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            'id' => 5,
            'title' => 'Test Title',
        ],
    ]);
});

test('resources may be converted to json with to json method', function () {
    $resource = new PostResource(new Post([
        'id' => 5,
        'title' => 'Test Title',
        'abstract' => 'Test abstract',
    ]));

    expect($resource->toJson())->toBe('{"id":5,"title":"Test Title","custom":true}');
});

test('an objects may be converted to json', function () {
    Route::get('/', function () {
        return ObjectResource::make(
            (object) ['first_name' => 'Bob', 'age' => 40]
        );
    });

    $this->withoutExceptionHandling()
        ->get('/', ['Accept' => 'application/json'])
        ->assertStatus(200)
        ->assertExactJson([
            'data' => [
                'name' => 'Bob',
                'age' => 40,
            ],
        ]);
});

test('arrays with objects may be converted to json', function () {
    Route::get('/', function () {
        $objects = [
            (object) ['first_name' => 'Bob', 'age' => 40],
            (object) ['first_name' => 'Jack', 'age' => 25],
        ];

        return ObjectResource::collection($objects);
    });

    $this->withoutExceptionHandling()
        ->get('/', ['Accept' => 'application/json'])
        ->assertStatus(200)
        ->assertExactJson([
            'data' => [
                ['name' => 'Bob', 'age' => 40],
                ['name' => 'Jack', 'age' => 25],
            ],
        ]);
});

test('resources may have no wrap', function () {
    Route::get('/', function () {
        return new PostResourceWithoutWrap(new Post([
            'id' => 5,
            'title' => 'Test Title',
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertJson([
        'id' => 5,
        'title' => 'Test Title',
    ]);
});

test('resources may have optional values', function () {
    Route::get('/', function () {
        return new PostResourceWithOptionalData(new Post([
            'id' => 5,
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            'id' => 5,
            'second' => 'value',
            'third' => 'value',
            'fourth' => 'default',
            'fifth' => 'default',
        ],
    ]);
});

test('resources may have optional values using unless', function () {
    Route::get('/', function () {
        return new PostResourceWithUnlessOptionalData(new Post([
            'id' => 5,
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/',
        ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            'id' => 5,
            'first' => 'value',
            'fourth' => 'value',
            'fifth' => 'value',
        ],
    ]);
});

test('resources may have optional selected attributes', function () {
    Route::get('/', function () {
        return new PostResourceWithOptionalAttributes(new Post([
            'id' => 5,
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            'id' => 5,
            'title' => 'no title',
        ],
    ]);
});

test('resources may have optional has attributes', function () {
    Route::get('/', function () {
        $post = new Post([
            'id' => 5,
            'is_published' => true,
        ]);

        return new PostResourceWithOptionalHasAttributes($post);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/',
        ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            'id' => 5,
            'first' => true,
            'second' => 'override value',
            'third' => 'override value',
            'fourth' => true,
            'fifth' => true,
        ],
    ]);
});

test('resources with optional has attributes return default values and not missing values', function () {
    Route::get('/', function () {
        return new PostResourceWithOptionalHasAttributes(new Post([
            'id' => 5,
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/',
        ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 5,
            'fourth' => 'default',
            'fifth' => 'default',
        ],
    ]);
});

test('resources may have optional appended attributes', function () {
    Route::get('/', function () {
        $post = new Post([
            'id' => 5,
        ]);

        $post->append('is_published');

        return new PostResourceWithOptionalAppendedAttributes($post);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            'id' => 5,
            'first' => true,
            'second' => 'override value',
            'third' => 'override value',
            'fourth' => true,
            'fifth' => true,
        ],
    ]);
});

test('resources with optional appended attributes return default values and not missing values', function () {
    Route::get('/', function () {
        return new PostResourceWithOptionalAppendedAttributes(new Post([
            'id' => 5,
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 5,
            'fourth' => 'default',
            'fifth' => 'default',
        ],
    ]);
});

test('resources may have optional merges', function () {
    Route::get('/', function () {
        return new PostResourceWithOptionalMerging(new Post([
            'id' => 5,
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 5,
            'second' => 'value',
        ],
    ]);
});

test('resources may have optional relationships', function () {
    Route::get('/', function () {
        return new PostResourceWithOptionalRelationship(new Post([
            'id' => 5,
            'title' => 'Test Title',
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 5,
        ],
    ]);
});

test('resources may have optional relationship counts', function () {
    Route::get('/', function () {
        $post = new Post([
            'id' => 5,
            'title' => 'Test Title',
        ]);

        return new PostResourceWithOptionalRelationshipCounts($post);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 5,
            'comments' => 'None',
        ],
    ]);
});

test('resources may load optional relationship counts', function () {
    Route::get('/', function () {
        $post = new Post([
            'id' => 5,
            'title' => 'Test Title',
            'authors_count' => 2,
            'comments_count' => 5,
            'favourited_posts_count' => 1,
        ]);

        return new PostResourceWithOptionalRelationshipCounts($post);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 5,
            'authors' => 2,
            'favourite_posts' => 1,
            'comments' => '5 comments',
        ],
    ]);
});

test('resources may have optional relationship exists', function () {
    Route::get('/', function () {
        return new PostResourceWithOptionalRelationshipExists(new Post([
            'id' => 5,
            'title' => 'Test Title',
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 5,
            'has_favourited_posts' => 'No',
        ],
    ]);
});

test('resources may load optional relationship exists', function () {
    Route::get('/', function () {
        $post = new Post([
            'id' => 5,
            'title' => 'Test Title',
            'authors_exists' => true,
            'favourited_posts_exists' => true,
            'comments_exists' => false,
        ]);

        return new PostResourceWithOptionalRelationshipExists($post);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 5,
            'has_authors' => true,
            'has_favourited_posts' => 'Yes',
            'comment_exists' => false,
        ],
    ]);
});

test('resources may load optional relationships', function () {
    Route::get('/', function () {
        $post = new Post([
            'id' => 5,
            'title' => 'Test Title',
        ]);

        $post->setRelation('author', new Author(['name' => 'jrrmartin']));

        return new PostResourceWithOptionalRelationship($post);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 5,
            'author' => ['name' => 'jrrmartin'],
            'author_name' => 'jrrmartin',
        ],
    ]);
});

test('resources may load optional relationship aggregates', function () {
    Route::get('/', function () {
        $post = new Post([
            'id' => 5,
            'title' => 'Test Title',
            'comments_avg_rating' => 3.8,
            'comments_min_rating' => 2,
            'comments_max_rating' => 5,
        ]);

        return new PostResourceWithOptionalRelationshipAggregates($post);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 5,
            'title' => 'Test Title',
            'average_rating' => 3.8,
            'minimum_rating' => 2,
            'maximum_rating' => '5 ratings',
        ],
    ]);
});

test('resources may have optional relationship aggregates', function () {
    Route::get('/', function () {
        $post = new Post([
            'id' => 5,
            'title' => 'Test Title',
        ]);

        return new PostResourceWithOptionalRelationshipAggregates($post);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 5,
            'title' => 'Test Title',
            'maximum_rating' => 'Default Value',
        ],
    ]);
});

test('resources may shows null for loaded relationship with value null', function () {
    Route::get('/', function () {
        $post = new Post([
            'id' => 5,
            'title' => 'Test Title',
        ]);

        $post->setRelation('author', null);

        return new PostResourceWithOptionalRelationship($post);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 5,
            'author' => null,
            'author_name' => null,
        ],
    ]);
});

test('resources may have optional relationships with default values', function () {
    Route::get('/', function () {
        return new AuthorResourceWithOptionalRelationship(new Author([
            'name' => 'jrrmartin',
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'name' => 'jrrmartin',
            'posts_count' => 'not loaded',
            'latest_post_title' => 'not loaded',
        ],
    ]);
});

test('resources may have optional pivot relationships', function () {
    Route::get('/', function () {
        $post = new Post(['id' => 5]);
        $post->setRelation('pivot', new Subscription);

        return new PostResourceWithOptionalPivotRelationship($post);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 5,
            'subscription' => [
                'foo' => 'bar',
            ],
        ],
    ]);
});

test('resource does not throw error when using eloquent strict mode and checking optional pivot relationship', function () {
    Model::shouldBeStrict(true);

    Route::get('/', function () {
        $post = new Post(['id' => 5]);
        (function () {
            $this->exists = true;
            $this->wasRecentlyCreated = false;
        })->bindTo($post)();

        return new PostResourceWithOptionalPivotRelationship($post);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 5,
        ],
    ]);
});

test('when loaded using named default parameter on missing relation', function () {
    Route::get('/', function () {
        $post = new Post(['id' => 1]);

        return new PostResourceWithOptionalRelationshipUsingNamedParameters($post);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 1,
            'author_defaulting_to_null' => null,
            'author_name' => 'Anonymous',
        ],
    ]);
});

test('when loaded using named default parameter on loaded relation', function () {
    Route::get('/', function () {
        $post = new Post(['id' => 1]);
        $post->setRelation('author', new Author(['name' => 'jrrmartin']));

        return new PostResourceWithOptionalRelationshipUsingNamedParameters($post);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 1,
            'author' => ['name' => 'jrrmartin'],
            'author_defaulting_to_null' => ['name' => 'jrrmartin'],
            'author_name' => 'jrrmartin',
        ],
    ]);
});

test('resources may have optional pivot relationships with custom accessor', function () {
    Route::get('/', function () {
        $post = new Post(['id' => 5]);
        $post->setRelation('accessor', new Subscription);

        return new PostResourceWithOptionalPivotRelationship($post);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertExactJson([
        'data' => [
            'id' => 5,
            'custom_subscription' => [
                'foo' => 'bar',
            ],
        ],
    ]);
});

test('resource is url routable', function () {
    $post = new PostResource(new Post([
        'id' => 5,
        'title' => 'Test Title',
    ]));

    expect(url('/post', $post))->toBe('http://localhost/post/5');
});

test('named routes are url routable', function () {
    $post = new PostResource(new Post([
        'id' => 5,
        'title' => 'Test Title',
    ]));

    Route::get('/post/{id}', function () use ($post) {
        return route('post.show', $post);
    })->name('post.show');

    $response = $this->withoutExceptionHandling()->get('/post/1');

    expect($response->original)->toBe('http://localhost/post/5');
});

test('resources may be serializable', function () {
    Route::get('/', function () {
        return new SerializablePostResource(new Post([
            'id' => 5,
            'title' => 'Test Title',
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            'id' => 5,
        ],
    ]);
});

test('resources may customize responses', function () {
    Route::get('/', function () {
        return new PostResource(new Post([
            'id' => 5,
            'title' => 'Test Title',
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);
    $response->assertHeader('X-Resource', 'True');
});

test('resources may customize extra data', function () {
    Route::get('/', function () {
        return new PostResourceWithExtraData(new Post([
            'id' => 5,
            'title' => 'Test Title',
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertJson([
        'data' => [
            'id' => 5,
            'title' => 'Test Title',
        ],
        'foo' => 'bar',
    ]);
});

test('resources may customize extra data when building response', function () {
    Route::get('/', function () {
        return (new PostResourceWithExtraData(new Post([
            'id' => 5,
            'title' => 'Test Title',
        ])))->additional(['baz' => 'qux']);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertJson([
        'data' => [
            'id' => 5,
            'title' => 'Test Title',
        ],
        'foo' => 'bar',
        'baz' => 'qux',
    ]);
});

test('resources may customize json options', function () {
    Route::get('/', function () {
        return new PostResourceWithJsonOptions(new Post([
            'id' => 5,
            'title' => 'Test Title',
            'reading_time' => 3.0,
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    expect($response->baseResponse->content())->toBe('{"data":{"id":5,"title":"Test Title","reading_time":3.0}}');
});

test('collection resources may customize json options', function () {
    Route::get('/', function () {
        return PostResourceWithJsonOptions::collection(collect([
            new Post(['id' => 5, 'title' => 'Test Title', 'reading_time' => 3.0]),
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    expect($response->baseResponse->content())->toBe('{"data":[{"id":5,"title":"Test Title","reading_time":3.0}]}');
});

test('resources may customize json options on paginated response', function () {
    Route::get('/', function () {
        $paginator = new LengthAwarePaginator(
            collect([new Post(['id' => 5, 'title' => 'Test Title', 'reading_time' => 3.0])]),
            10, 15, 1
        );

        return PostResourceWithJsonOptions::collection($paginator);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    expect($response->baseResponse->content())->toBe('{"data":[{"id":5,"title":"Test Title","reading_time":3.0}],"links":{"first":"\/?page=1","last":"\/?page=1","prev":null,"next":null},"meta":{"current_page":1,"from":1,"last_page":1,"links":[{"url":null,"label":"&laquo; Previous","page":null,"active":false},{"url":"\/?page=1","label":"1","page":1,"active":true},{"url":null,"label":"Next &raquo;","page":null,"active":false}],"path":"\/","per_page":15,"to":1,"total":10}}');
});

test('resources may customize json options with type hinted constructor', function () {
    Route::get('/', function () {
        return new PostResourceWithJsonOptionsAndTypeHints(new Post([
            'id' => 5,
            'title' => 'Test Title',
            'reading_time' => 3.0,
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    expect($response->baseResponse->content())->toBe('{"data":{"id":5,"title":"Test Title","reading_time":3.0}}');
});

test('custom headers may be set on responses', function () {
    Route::get('/', function () {
        return (new PostResource(new Post([
            'id' => 5,
            'title' => 'Test Title',
        ])))->response()->setStatusCode(202)->header('X-Custom', 'True');
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(202);
    $response->assertHeader('X-Custom', 'True');
});

test('resources may receive proper status code for fresh models', function () {
    Route::get('/', function () {
        $post = new Post([
            'id' => 5,
            'title' => 'Test Title',
        ]);

        $post->wasRecentlyCreated = true;

        return new PostResource($post);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(201);
});

test('collections are not doubled wrapped', function () {
    Route::get('/', function () {
        return new PostCollectionResource(collect([new Post([
            'id' => 5,
            'title' => 'Test Title',
        ])]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            [
                'id' => 5,
                'title' => 'Test Title',
            ],
        ],
    ]);
});

test('paginators receive links', function () {
    Route::get('/', function () {
        $paginator = new LengthAwarePaginator(
            collect([new Post(['id' => 5, 'title' => 'Test Title'])]),
            10, 15, 1
        );

        return new PostCollectionResource($paginator);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            [
                'id' => 5,
                'title' => 'Test Title',
            ],
        ],
        'links' => [
            'first' => '/?page=1',
            'last' => '/?page=1',
            'prev' => null,
            'next' => null,
        ],
        'meta' => [
            'current_page' => 1,
            'from' => 1,
            'last_page' => 1,
            'path' => '/',
            'per_page' => 15,
            'to' => 1,
            'total' => 10,
        ],
    ]);
});

test('paginator resource can preserve query parameters', function () {
    Route::get('/', function () {
        $collection = collect([new Post(['id' => 2, 'title' => 'Laravel Nova'])]);
        $paginator = new LengthAwarePaginator(
            $collection, 3, 1, 2
        );

        return PostCollectionResource::make($paginator)->preserveQuery();
    });

    $response = $this->withoutExceptionHandling()->get(
        '/?framework=laravel&author=Otwell&page=2', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            [
                'id' => 2,
                'title' => 'Laravel Nova',
            ],
        ],
        'links' => [
            'first' => '/?framework=laravel&author=Otwell&page=1',
            'last' => '/?framework=laravel&author=Otwell&page=3',
            'prev' => '/?framework=laravel&author=Otwell&page=1',
            'next' => '/?framework=laravel&author=Otwell&page=3',
        ],
        'meta' => [
            'current_page' => 2,
            'from' => 2,
            'last_page' => 3,
            'path' => '/',
            'per_page' => 1,
            'to' => 2,
            'total' => 3,
        ],
    ]);
});

test('paginator resource can receive query parameters', function () {
    Route::get('/', function () {
        $collection = collect([new Post(['id' => 2, 'title' => 'Laravel Nova'])]);
        $paginator = new LengthAwarePaginator(
            $collection, 3, 1, 2
        );

        return PostCollectionResource::make($paginator)->withQuery(['author' => 'Taylor']);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/?framework=laravel&author=Otwell&page=2', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            [
                'id' => 2,
                'title' => 'Laravel Nova',
            ],
        ],
        'links' => [
            'first' => '/?author=Taylor&page=1',
            'last' => '/?author=Taylor&page=3',
            'prev' => '/?author=Taylor&page=1',
            'next' => '/?author=Taylor&page=3',
        ],
        'meta' => [
            'current_page' => 2,
            'from' => 2,
            'last_page' => 3,
            'path' => '/',
            'per_page' => 1,
            'to' => 2,
            'total' => 3,
        ],
    ]);
});

test('cursor paginator receive links', function () {
    Route::get('/', function () {
        $paginator = new CursorPaginator(
            collect([new Post(['id' => 5, 'title' => 'Test Title']), new Post(['id' => 6, 'title' => 'Hello'])]),
            1, null, ['parameters' => ['id']]
        );

        return new PostCollectionResource($paginator);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            [
                'id' => 5,
                'title' => 'Test Title',
            ],
        ],
        'links' => [
            'first' => null,
            'last' => null,
            'prev' => null,
            'next' => '/?cursor='.(new Cursor(['id' => 5]))->encode(),
        ],
        'meta' => [
            'path' => '/',
            'per_page' => 1,
            'next_cursor' => (new Cursor(['id' => 5]))->encode(),
            'prev_cursor' => null,
        ],
    ]);
});

test('cursor paginator resource can preserve query parameters', function () {
    Route::get('/', function () {
        $collection = collect([new Post(['id' => 5, 'title' => 'Test Title']), new Post(['id' => 6, 'title' => 'Hello'])]);
        $paginator = new CursorPaginator(
            $collection, 1, null, ['parameters' => ['id']]
        );

        return PostCollectionResource::make($paginator)->preserveQuery();
    });

    $response = $this->withoutExceptionHandling()->get(
        '/?framework=laravel&author=Otwell', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            [
                'id' => 5,
                'title' => 'Test Title',
            ],
        ],
        'links' => [
            'first' => null,
            'last' => null,
            'prev' => null,
            'next' => '/?framework=laravel&author=Otwell&cursor='.(new Cursor(['id' => 5]))->encode(),
        ],
        'meta' => [
            'path' => '/',
            'per_page' => 1,
        ],
    ]);
});

test('cursor paginator resource can receive query parameters', function () {
    Route::get('/', function () {
        $collection = collect([new Post(['id' => 5, 'title' => 'Test Title']), new Post(['id' => 6, 'title' => 'Hello'])]);
        $paginator = new CursorPaginator(
            $collection, 1, null, ['parameters' => ['id']]
        );

        return PostCollectionResource::make($paginator)->withQuery(['author' => 'Taylor']);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/?framework=laravel&author=Otwell', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            [
                'id' => 5,
                'title' => 'Test Title',
            ],
        ],
        'links' => [
            'first' => null,
            'last' => null,
            'prev' => null,
            'next' => '/?author=Taylor&cursor='.(new Cursor(['id' => 5]))->encode(),
        ],
        'meta' => [
            'path' => '/',
            'per_page' => 1,
        ],
    ]);
});

test('to json may be left off of collection', function () {
    Route::get('/', function () {
        return new EmptyPostCollectionResource(new LengthAwarePaginator(
            collect([new Post(['id' => 5, 'title' => 'Test Title'])]),
            10, 15, 1
        ));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            [
                'id' => 5,
                'title' => 'Test Title',
                'custom' => true,
            ],
        ],
        'links' => [
            'first' => '/?page=1',
            'last' => '/?page=1',
            'prev' => null,
            'next' => null,
        ],
        'meta' => [
            'current_page' => 1,
            'from' => 1,
            'last_page' => 1,
            'path' => '/',
            'per_page' => 15,
            'to' => 1,
            'total' => 10,
        ],
    ]);
});

test('to json may be left off of single resource', function () {
    Route::get('/', function () {
        return new ReallyEmptyPostResource(new Post([
            'id' => 5,
            'title' => 'Test Title',
        ]));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            'id' => 5,
            'title' => 'Test Title',
        ],
    ]);
});

test('original on response is model when single resource', function () {
    $createdPost = new Post(['id' => 5, 'title' => 'Test Title']);
    Route::get('/', function () use ($createdPost) {
        return new ReallyEmptyPostResource($createdPost);
    });
    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );
    expect($createdPost->is($response->getOriginalContent()))->toBeTrue();
});

test('original on response is collection of model when collection resource', function () {
    $createdPosts = collect([
        new Post(['id' => 5, 'title' => 'Test Title']),
        new Post(['id' => 6, 'title' => 'Test Title 2']),
    ]);
    Route::get('/', function () use ($createdPosts) {
        return new EmptyPostCollectionResource(new LengthAwarePaginator($createdPosts, 10, 15, 1));
    });
    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );
    $createdPosts->each(function ($post) use ($response) {
        expect($response->getOriginalContent()->contains($post))->toBeTrue();
    });
});

test('collection resource with pagination information', function () {
    $posts = collect([
        new Post(['id' => 5, 'title' => 'Test Title']),
    ]);

    Route::get('/', function () use ($posts) {
        return new PostCollectionResourceWithPaginationInformation(new LengthAwarePaginator($posts, 10, 1, 1));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/',
        ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            [
                'id' => 5,
                'title' => 'Test Title',
            ],
        ],
        'current_page' => 1,
        'per_page' => 1,
        'total_page' => 10,
        'total' => 10,
    ]);
});

test('resource with pagination information', function () {
    $posts = collect([
        new Post(['id' => 5, 'title' => 'Test Title']),
    ]);

    Route::get('/', function () use ($posts) {
        return PostResourceWithAnonymousResourceCollectionWithPaginationInformation::collection(new LengthAwarePaginator($posts, 10, 1, 1));
    });

    $response = $this->withoutExceptionHandling()->get(
        '/',
        ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson([
        'data' => [
            [
                'id' => 5,
                'title' => 'Test Title',
            ],
        ],
        'current_page' => 1,
        'per_page' => 1,
        'total_page' => 10,
        'total' => 10,
    ]);
});

test('collection resources are countable', function () {
    $posts = collect([
        new Post(['id' => 1, 'title' => 'Test title']),
        new Post(['id' => 2, 'title' => 'Test title 2']),
    ]);

    $collection = new PostCollectionResource($posts);

    expect($collection)->toHaveCount(2);
    expect($collection)->toHaveCount(2);
});

test('collection resources must collect resources', function () {
    $posts = collect([
        new Post(['id' => 1, 'title' => 'Test title']),
        new Post(['id' => 2, 'title' => 'Test title 2']),
    ]);

    new PostModelCollectionResource($posts);
})->throws(LogicException::class, 'must collect');

test('keys are preserved if the resource is flagged to preserve keys', function () {
    $data = [
        'authorBook' => [
            'byId' => [
                1 => [
                    'id' => 1,
                    'authorId' => 5,
                    'bookId' => 22,
                ],
                2 => [
                    'id' => 2,
                    'authorId' => 5,
                    'bookId' => 15,
                ],
                3 => [
                    'id' => 3,
                    'authorId' => 42,
                    'bookId' => 12,
                ],
            ],
            'allIds' => [1, 2, 3],
        ],
    ];

    Route::get('/', function () use ($data) {
        return new ResourceWithPreservedKeys($data);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson(['data' => $data]);
});

test('keys are preserved in an anonymous collection if the resource is flagged to preserve keys', function () {
    $data = (new Collection([
        [
            'id' => 1,
            'authorId' => 5,
            'bookId' => 22,
        ],
        [
            'id' => 2,
            'authorId' => 5,
            'bookId' => 15,
        ],
        [
            'id' => 3,
            'authorId' => 42,
            'bookId' => 12,
        ],
    ]))->keyBy->id;

    Route::get('/', function () use ($data) {
        return ResourceWithPreservedKeys::collection($data);
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson(['data' => $data->toArray()]);
});

test('keys are preserved in an anonymous collection using preserve keys method', function () {
    $data = (new Collection([
        ['id' => 1, 'title' => 'Test'],
        ['id' => 2, 'title' => 'Test 2'],
    ]))->keyBy->id;

    Route::get('/', function () use ($data) {
        return JsonResource::collection($data)->preserveKeys();
    });

    $response = $this->withoutExceptionHandling()->get(
        '/', ['Accept' => 'application/json']
    );

    $response->assertStatus(200);

    $response->assertJson(['data' => $data->toArray()]);
});

test('leading merge keyed value is merged correctly', function () {
    $filter = new class
    {
        use ConditionallyLoadsAttributes;

        public function work()
        {
            return $this->filter([
                new MergeValue(['name' => 'mohamed', 'location' => 'hurghada']),
            ]);
        }
    };

    $results = $filter->work();

    expect($results)->toEqual([
        'name' => 'mohamed', 'location' => 'hurghada',
    ]);
});

test('post too large exception', function () {
    $request = new Request(server: ['CONTENT_LENGTH' => '4']);
    $post = new ValidatePostSize;
    $post->handle($request, fn () => null);

    $request = new Request(server: ['CONTENT_LENGTH' => '2147483640']);
    $post = new ValidatePostSize;
    $post->handle($request, fn () => null);
})->throws(PostTooLargeException::class, 'The POST data is too large.');

test('leading merge keyed value is merged correctly when first value is missing', function () {
    $filter = new class
    {
        use ConditionallyLoadsAttributes;

        public function work()
        {
            return $this->filter([
                new MergeValue([
                    0 => new MissingValue,
                    'name' => 'mohamed',
                    'location' => 'hurghada',
                ]),
            ]);
        }
    };

    $results = $filter->work();

    expect($results)->toEqual([
        'name' => 'mohamed', 'location' => 'hurghada',
    ]);
});

test('leading merge value is merged correctly', function () {
    $filter = new class
    {
        use ConditionallyLoadsAttributes;

        public function work()
        {
            return $this->filter([
                new MergeValue(['First', 'Second']),
                'Taylor',
                'Mohamed',
                new MergeValue(['Adam', 'Matt']),
                'Jeffrey',
                new MergeValue(['Abigail', 'Lydia']),
            ]);
        }
    };

    $results = $filter->work();

    expect($results)->toEqual([
        'First', 'Second', 'Taylor', 'Mohamed', 'Adam', 'Matt', 'Jeffrey', 'Abigail', 'Lydia',
    ]);
});

test('merge values may be missing', function () {
    $filter = new class
    {
        use ConditionallyLoadsAttributes;

        public function work()
        {
            return $this->filter([
                new MergeValue(['First', 'Second']),
                'Taylor',
                'Mohamed',
                $this->mergeWhen(false, ['Adam', 'Matt']),
                'Jeffrey',
                new MergeValue(['Abigail', 'Lydia']),
            ]);
        }
    };

    $results = $filter->work();

    expect($results)->toEqual([
        'First', 'Second', 'Taylor', 'Mohamed', 'Jeffrey', 'Abigail', 'Lydia',
    ]);
});

test('initial merge values may be missing', function () {
    $filter = new class
    {
        use ConditionallyLoadsAttributes;

        public function work()
        {
            return $this->filter([
                $this->mergeWhen(false, ['First', 'Second']),
                'Taylor',
                'Mohamed',
                $this->mergeWhen(true, ['Adam', 'Matt']),
                'Jeffrey',
                new MergeValue(['Abigail', 'Lydia']),
            ]);
        }
    };

    $results = $filter->work();

    expect($results)->toEqual([
        'Taylor', 'Mohamed', 'Adam', 'Matt', 'Jeffrey', 'Abigail', 'Lydia',
    ]);
});

test('merge value can merge json serializable', function () {
    $filter = new class
    {
        use ConditionallyLoadsAttributes;

        public function work()
        {
            $postResource = new PostResource(new Post([
                'id' => 1,
                'title' => 'Test Title 1',
            ]));

            return $this->filter([
                new MergeValue($postResource),
                'user' => 'test user',
                'age' => 'test age',
            ]);
        }
    };

    $results = $filter->work();

    expect($results)->toEqual([
        'id' => 1,
        'title' => 'Test Title 1',
        'custom' => true,
        'user' => 'test user',
        'age' => 'test age',
    ]);
});

test('merge value can merge collection of json serializable', function () {
    $filter = new class
    {
        use ConditionallyLoadsAttributes;

        public function work()
        {
            $posts = collect([
                new Post(['id' => 1, 'title' => 'Test title 1']),
                new Post(['id' => 2, 'title' => 'Test title 2']),
            ]);

            return $this->filter([
                new MergeValue(PostResource::collection($posts)),
            ]);
        }
    };

    $results = $filter->work();

    expect($results)->toEqual([
        ['id' => 1, 'title' => 'Test title 1', 'custom' => true],
        ['id' => 2, 'title' => 'Test title 2', 'custom' => true],
    ]);
});

test('all merge values may be missing', function () {
    $filter = new class
    {
        use ConditionallyLoadsAttributes;

        public function work()
        {
            return $this->filter([
                $this->mergeWhen(false, ['First', 'Second']),
                'Taylor',
                'Mohamed',
                $this->mergeWhen(false, ['Adam', 'Matt']),
                'Jeffrey',
                $this->mergeWhen(false, ['Abigail', 'Lydia']),
            ]);
        }
    };

    $results = $filter->work();

    expect($results)->toEqual([
        'Taylor', 'Mohamed', 'Jeffrey',
    ]);
});

test('merge values may fallback to defaults', function () {
    $filter = new class
    {
        use ConditionallyLoadsAttributes;

        public function work()
        {
            return $this->filter([
                $this->mergeUnless(false, ['Taylor', 'Mohamed'], ['First', 'Second']),
                $this->mergeWhen(false, ['Adam', 'Matt'], ['Abigail', 'Lydia']),
                'Jeffrey',
            ]);
        }
    };

    $results = $filter->work();

    expect($results)->toEqual([
        'Taylor', 'Mohamed', 'Abigail', 'Lydia', 'Jeffrey',
    ]);
});

test('nested merges', function () {
    $filter = new class
    {
        use ConditionallyLoadsAttributes;

        public function work()
        {
            return $this->filter([
                $this->mergeWhen(true, [['Something']]),
                [
                    $this->mergeWhen(true, ['First', $this->mergeWhen(true, ['Second'])]),
                    'Third',
                ],
                [
                    'Fourth',
                ],
            ]);
        }
    };

    $results = $filter->work();

    expect($results)->toEqual([
        [
            'Something',
        ],
        [
            'First', 'Second', 'Third',
        ],
        [
            'Fourth',
        ],
    ]);
});

test('the resource can be an array', function () {
    $this->withoutExceptionHandling();
    assertJsonResourceResponse($this, [
        'user@example.com' => 'John',
        'admin@example.com' => 'Hank',
    ], [
        'data' => [
            'user@example.com' => 'John',
            'admin@example.com' => 'Hank',
        ],
    ]);
});

test('it will return as an array when string keys are stripped', function () {
    $this->withoutExceptionHandling();
    assertJsonResourceResponse($this, [
        1 => 'John',
        2 => 'Hank',
        'foo' => new MissingValue,
    ], ['data' => ['John', 'Hank']]);

    $this->withoutExceptionHandling();

    assertJsonResourceResponse($this, [
        1 => 'John',
        'foo' => new MissingValue,
        3 => 'Hank',
    ], ['data' => ['John', 'Hank']]);

    $this->withoutExceptionHandling();

    assertJsonResourceResponse($this, [
        'foo' => new MissingValue,
        2 => 'John',
        3 => 'Hank',
    ], ['data' => ['John', 'Hank']]);
});

test('it strips numeric keys', function () {
    $this->withoutExceptionHandling();
    assertJsonResourceResponse($this, [
        0 => 'John',
        1 => 'Hank',
    ], ['data' => ['John', 'Hank']]);

    $this->withoutExceptionHandling();

    assertJsonResourceResponse($this, [
        0 => 'John',
        1 => 'Hank',
        3 => 'Bill',
    ], ['data' => ['John', 'Hank', 'Bill']]);

    $this->withoutExceptionHandling();

    assertJsonResourceResponse($this, [
        5 => 'John',
        6 => 'Hank',
    ], ['data' => ['John', 'Hank']]);
});

test('it wont keys if any of them are strings', function () {
    $this->withoutExceptionHandling();
    assertJsonResourceResponse($this, [
        '5' => 'John',
        '6' => 'Hank',
        'a' => 'Bill',
    ], ['data' => ['5' => 'John', '6' => 'Hank', 'a' => 'Bill']]);

    $this->withoutExceptionHandling();

    assertJsonResourceResponse($this, [
        0 => 10,
        1 => 20,
        'total' => 30,
    ], ['data' => [0 => 10, 1 => 20, 'total' => 30]]);
});

test('it throws no error in strict mode when resource is paginated', function () {
    $originalMode = Model::preventsAccessingMissingAttributes();
    Model::preventAccessingMissingAttributes();
    try {
        Route::get('/', function () {
            $paginator = new LengthAwarePaginator(
                collect([new Post(['id' => 5, 'title' => 'Test Title', 'reading_time' => 3.0])]),
                10, 15, 1
            );

            return PostResourceWithJsonOptions::collection($paginator);
        });

        $response = $this->withoutExceptionHandling()->get(
            '/', ['Accept' => 'application/json']
        );

        $response->assertStatus(200);
    } finally {
        Model::preventAccessingMissingAttributes($originalMode);
    }
});

test('resource skips wrapping when data key exists', function () {
    $resource = new class(['id' => 5, 'title' => 'Test', 'data' => 'some data']) extends JsonResource
    {
        public static $wrap = 'data';
    };

    $response = $resource->toResponse(request());
    $content = json_decode($response->getContent(), true);

    expect($content)->toEqual([
        'id' => 5,
        'title' => 'Test',
        'data' => 'some data',
    ]);
});

test('resource wraps when data key does not exist', function () {
    $resource = new class(['id' => 5, 'title' => 'Test']) extends JsonResource
    {
        public static $wrap = 'data';
    };

    $response = $resource->toResponse(request());
    $content = json_decode($response->getContent(), true);

    expect($content)->toEqual([
        'data' => [
            'id' => 5,
            'title' => 'Test',
        ],
    ]);
});

test('resource can overrides wrapping', function () {
    $resource = new class(['id' => 5, 'title' => 'Test', 'data' => 'some data']) extends JsonResource
    {
        public static $wrap = 'results';
        public static bool $forceWrapping = true;
    };

    JsonResource::flushState();

    $response = $resource->toResponse(request());
    $content = json_decode($response->getContent(), true);

    expect($content)->toEqual([
        'results' => [
            'id' => 5,
            'title' => 'Test',
            'data' => 'some data',
        ],
    ]);
});

test('resource collection can overrides wrapping', function () {
    $resource = new class([new class(['id' => 5, 'title' => 'Test', 'data' => 'some data']) extends JsonResource
    {
        public static $wrap = null;
    },
    ]) extends ResourceCollection {
        public static $wrap = 'results';
    };

    JsonResource::flushState();

    $response = $resource->toResponse(request());
    $content = json_decode($response->getContent(), true);

    expect($content)->toEqual([
        'results' => [
            [
                'id' => 5,
                'title' => 'Test',
                'data' => 'some data',
            ],
        ],
    ]);
});

test('paginated resource collection can overrides wrapping', function () {
    $resource = new class(new LengthAwarePaginator([new class(['id' => 5, 'title' => 'Test', 'data' => 'some data']) extends JsonResource
    {
        public static $wrap = null;
    },
    ], 10, 2)) extends ResourceCollection {
        public static $wrap = 'results';
    };

    JsonResource::flushState();

    $response = $resource->toResponse(request());
    $content = json_decode($response->getContent(), true);

    expect($content)->toHaveKey('results');
    expect($content)->toHaveKey('links');
    expect($content)->toHaveKey('meta');

    expect($content['results'])->toHaveCount(1);
    expect($content['results'])->toEqual([
        [
            'id' => 5,
            'title' => 'Test',
            'data' => 'some data',
        ],
    ]);
});

test('empty paginated resource collection can overrides wrapping', function () {
    $resource = new class(new LengthAwarePaginator([], 10, 2)) extends ResourceCollection
    {
        public static $wrap = 'results';
    };

    JsonResource::flushState();

    $response = $resource->toResponse(request());
    $content = json_decode($response->getContent(), true);

    expect($content)->toHaveKey('results');
    expect($content)->toHaveKey('links');
    expect($content)->toHaveKey('meta');

    expect($content['results'])->toHaveCount(0);
});

test('resource force wrap overrides data key check', function () {
    $resource = new class(['id' => 5, 'title' => 'Test', 'data' => 'some data']) extends JsonResource
    {
        public static $wrap = 'data';
        public static bool $forceWrapping = true;
    };

    $response = $resource->toResponse(request());
    $content = json_decode($response->getContent(), true);

    expect($content)->toEqual([
        'data' => [
            'id' => 5,
            'title' => 'Test',
            'data' => 'some data',
        ],
    ]);
});

function assertJsonResourceResponse($test, $data, $expectedJson)
{
    Route::get('/', function () use ($data) {
        return new JsonResource($data);
    });

    $test->get('/', ['Accept' => 'application/json'])
        ->assertStatus(200)
        ->assertExactJson($expectedJson);
}
