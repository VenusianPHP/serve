<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Voyager\Database\Instrument\Concerns\HasUlids;
use Voyager\Database\Instrument\Concerns\HasUuids;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\SoftDeletes;
use Mariner\NutsAndBolts\Facades\Route;
use Venusian\Serve\Tests\Integration\IntegrationTestCase;
use Voyager\Database\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeAll(fn () => IntegrationTestCase::withMigrations('routing-binding'));
afterAll(fn () => IntegrationTestCase::withMigrations());

beforeEach(fn () => $this->app['config']->set('app.key', 'AckfSECXIvnK5r28GVIWUAxmbBSjTsmF'));

test('with route caching enabled', function () {
    $this->defineCacheRoutes(<<<PHP
<?php

use Mariner\NutsAndBolts\Facades\Route;
use Venusian\Serve\Tests\Integration\Routing\ImplicitBindingUser;

Route::post('/user/{user}', function (ImplicitBindingUser \$user) {
return \$user;
})->middleware('web');
PHP);

    $user = ImplicitBindingUser::create(['name' => 'Dries']);

    $response = $this->postJson("/user/{$user->id}");

    $response->assertJson([
        'id' => $user->id,
        'name' => $user->name,
    ]);
});

test('without route caching enabled', function () {
    $user = ImplicitBindingUser::create(['name' => 'Dries']);

    config(['app.key' => str_repeat('a', 32)]);

    Route::post('/user/{user}', function (ImplicitBindingUser $user) {
        return $user;
    })->middleware(['web']);

    $response = $this->postJson("/user/{$user->id}");

    $response->assertJson([
        'id' => $user->id,
        'name' => $user->name,
    ]);

    expect($user->is($response->baseRequest->route('user')))->toBeTrue();
});

test('soft deleted models are not retrieved', function () {
    $user = ImplicitBindingUser::create(['name' => 'Dries']);

    $user->delete();

    config(['app.key' => str_repeat('a', 32)]);

    Route::post('/user/{user}', function (ImplicitBindingUser $user) {
        return $user;
    })->middleware(['web']);

    $response = $this->postJson("/user/{$user->id}");

    $response->assertStatus(404);
});

test('soft deleted models can be retrieved using with trashed method', function () {
    $user = ImplicitBindingUser::create(['name' => 'Dries']);

    $user->delete();

    config(['app.key' => str_repeat('a', 32)]);

    Route::post('/user/{user}', function (ImplicitBindingUser $user) {
        return $user;
    })->middleware(['web'])->withTrashed();

    $response = $this->postJson("/user/{$user->id}");

    $response->assertJson([
        'id' => $user->id,
        'name' => $user->name,
    ]);

    expect($user->is($response->baseRequest->route('user')))->toBeTrue();
});

test('enforce scoping implicit route bindings', function () {
    $user = ImplicitBindingUser::create(['name' => 'Dries']);
    $post = ImplicitBindingPost::create(['user_id' => 2]);
    expect($user->posts)->toBeEmpty();

    config(['app.key' => str_repeat('a', 32)]);

    Route::scopeBindings()->group(function () {
        Route::get('/user/{user}/post/{post}', function (ImplicitBindingUser $user, ImplicitBindingPost $post) {
            return [$user, $post];
        })->middleware(['web']);
    });

    $response = $this->getJson("/user/{$user->id}/post/{$post->id}");

    $response->assertNotFound();
});

test('enforce scoping implicit route bindings with trashed and child with no soft delete trait', function () {
    $user = ImplicitBindingUser::create(['name' => 'Dries']);

    $post = $user->posts()->create();

    $user->delete();

    config(['app.key' => str_repeat('a', 32)]);
    Route::scopeBindings()->group(function () {
        Route::get('/user/{user}/post/{post}', function (ImplicitBindingUser $user, ImplicitBindingPost $post) {
            return [$user, $post];
        })->middleware(['web'])->withTrashed();
    });

    $response = $this->getJson("/user/{$user->id}/post/{$post->id}");
    $response->assertOk();
    $response->assertJson([
        [
            'id' => $user->id,
            'name' => $user->name,
        ],
        [
            'id' => 1,
            'user_id' => 1,
        ],
    ]);
});

test('enforce scoping implicit route bindings with route caching enabled', function () {
    $user = ImplicitBindingUser::create(['name' => 'Dries']);
    $post = ImplicitBindingPost::create(['user_id' => 2]);
    expect($user->posts)->toBeEmpty();

    $this->defineCacheRoutes(<<<PHP
<?php

use Mariner\NutsAndBolts\Facades\Route;
use Venusian\Serve\Tests\Integration\Routing\ImplicitBindingUser;
use Venusian\Serve\Tests\Integration\Routing\ImplicitBindingPost;

Route::group(['scope_bindings' => true], function () {
Route::get('/user/{user}/post/{post}', function (ImplicitBindingUser \$user, ImplicitBindingPost \$post) {
    return [\$user, \$post];
})->middleware(['web']);
});
PHP);

    $response = $this->getJson("/user/{$user->id}/post/{$post->id}");

    $response->assertNotFound();
});

test('without enforce scoping implicit route bindings', function () {
    $user = ImplicitBindingUser::create(['name' => 'Dries']);
    $post = ImplicitBindingPost::create(['user_id' => 2]);
    expect($user->posts)->toBeEmpty();

    config(['app.key' => str_repeat('a', 32)]);

    Route::group(['scope_bindings' => false], function () {
        Route::get('/user/{user}/post/{post}', function (ImplicitBindingUser $user, ImplicitBindingPost $post) {
            return [$user, $post];
        })->middleware(['web']);
    });

    $response = $this->getJson("/user/{$user->id}/post/{$post->id}");
    $response->assertOk();
    $response->assertJson([
        [
            'id' => $user->id,
            'name' => $user->name,
        ],
        [
            'id' => 1,
            'user_id' => 2,
        ],
    ]);
});

test('implicit route binding child has uuids', function () {
    $user = ImplicitBindingUser::create(['name' => 'Dries']);
    $comment = ImplicitBindingComment::create([
        'slug' => 'slug',
        'user_id' => $user->id,
    ]);

    config(['app.key' => str_repeat('a', 32)]);

    $function = function (ImplicitBindingUser $user, ImplicitBindingComment $comment) {
        return [$user, $comment];
    };

    Route::middleware(['web'])->group(function () use ($function) {
        Route::get('/user/{user}/comment/{comment}', $function);
        Route::get('/user/{user}/comment-id/{comment:id}', $function);
        Route::get('/user/{user}/comment-slug/{comment:slug}', $function);
    });

    $response = $this->getJson("/user/{$user->id}/comment/{$comment->slug}");
    $response->assertJsonFragment(['id' => $comment->id]);

    $response = $this->getJson("/user/{$user->id}/comment-id/{$comment->id}");
    $response->assertJsonFragment(['id' => $comment->id]);

    $response = $this->getJson("/user/{$user->id}/comment-slug/{$comment->slug}");
    $response->assertJsonFragment(['id' => $comment->id]);
});

test('implicit route binding child has ulids', function () {
    $user = ImplicitBindingUser::create(['name' => 'Michael Nabil']);
    $post = ImplicitBindingPost::create(['user_id' => $user->id]);
    $tag = ImplicitBindingTag::create([
        'slug' => 'slug',
        'post_id' => $post->id,
    ]);

    config(['app.key' => str_repeat('a', 32)]);

    $function = function (ImplicitBindingPost $post, ImplicitBindingTag $tag) {
        return [$post, $tag];
    };

    Route::middleware(['web'])->group(function () use ($function) {
        Route::get('/post/{post}/tag/{tag}', $function);
        Route::get('/post/{post}/tag-id/{tag:id}', $function);
        Route::get('/post/{post}/tag-slug/{tag:slug}', $function);
    });

    $response = $this->getJson("/post/{$post->id}/tag/{$tag->slug}");
    $response->assertJsonFragment(['id' => $tag->id]);

    $response = $this->getJson("/post/{$post->id}/tag-id/{$tag->id}");
    $response->assertJsonFragment(['id' => $tag->id]);

    $response = $this->getJson("/post/{$post->id}/tag-slug/{$tag->slug}");
    $response->assertJsonFragment(['id' => $tag->id]);
});

class ImplicitBindingUser extends Model
{
    use SoftDeletes;

    public $table = 'users';

    protected $fillable = ['name'];

    public function posts()
    {
        return $this->hasMany(ImplicitBindingPost::class, 'user_id');
    }

    public function comments()
    {
        return $this->hasMany(ImplicitBindingComment::class, 'user_id');
    }
}

class ImplicitBindingPost extends Model
{
    public $table = 'posts';

    protected $fillable = ['user_id'];

    public function tags()
    {
        return $this->hasMany(ImplicitBindingTag::class, 'post_id');
    }
}

class ImplicitBindingTag extends Model
{
    use HasUlids;

    public $table = 'tags';

    protected $fillable = ['slug', 'post_id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}

class ImplicitBindingComment extends Model
{
    use HasUuids;

    public $table = 'comments';

    protected $fillable = ['slug', 'user_id'];

    public function getRouteKeyName(): string
    {
        return 'slug';
    }
}
