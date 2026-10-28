<?php

use Mariner\Http\Request;
use Mariner\Routing\Middleware\SubstituteBindings;
use Venusian\Serve\Tests\Fixtures\Article;
use Venusian\Serve\Tests\Fixtures\Author;
use Venusian\Serve\Tests\Fixtures\PublishedArticle;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Database\Schema\Blueprint;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->app['config']->set('database.connections.binding', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);

    $schema = $this->app['db']->connection('binding')->getSchemaBuilder();
    $schema->create('authors', function (Blueprint $table) {
        $table->id();
        $table->string('slug');
    });
    $schema->create('articles', function (Blueprint $table) {
        $table->id();
        $table->foreignId('author_id');
        $table->string('slug');
        $table->boolean('published')->default(true);
        $table->softDeletes();
    });

    $ada = Author::create(['slug' => 'ada']);
    $grace = Author::create(['slug' => 'grace']);
    Article::create(['author_id' => $ada->id, 'slug' => 'engines']);
    Article::create(['author_id' => $grace->id, 'slug' => 'compilers']);
    Article::create(['author_id' => $ada->id, 'slug' => 'draft', 'published' => false]);
    Article::create(['author_id' => $ada->id, 'slug' => 'removed'])->delete();

    $this->router = $this->app['router'];
});

afterEach(fn () => WebApp::tearDown($this));

/** Dispatches a GET through the router (with binding substitution) and returns the response. */
function get($app, string $uri)
{
    $request = Request::create($uri);
    $app->registerInstance('request', $request);

    return $app['router']->dispatch($request);
}

it('binds an Instrument model by its route key', function () {
    $this->router->get('authors/{author}', fn (Author $author) => $author->slug)->middleware(SubstituteBindings::class);

    expect(get($this->app, '/authors/2')->getContent())->toBe('grace');
});

it('binds by a custom field', function () {
    $this->router->get('authors/{author:slug}', fn (Author $author) => (string) $author->id)->middleware(SubstituteBindings::class);

    expect(get($this->app, '/authors/ada')->getContent())->toBe('1');
});

it('scopes a child binding through the parent\'s relationship', function () {
    $this->router->get('authors/{author}/articles/{article:slug}', fn (Author $author, Article $article) => $article->slug)
        ->middleware(SubstituteBindings::class);

    expect(get($this->app, '/authors/1/articles/engines')->getContent())->toBe('engines')
        ->and(get($this->app, '/authors/1/articles/compilers')->getStatusCode())->toBe(404);
});

it('binds trashed models only where the route allows them', function () {
    $this->router->get('articles/{article:slug}', fn (Article $article) => $article->slug)->middleware(SubstituteBindings::class);
    $this->router->get('trashed/{article:slug}', fn (Article $article) => $article->slug)->middleware(SubstituteBindings::class)->withTrashed();

    expect(get($this->app, '/trashed/removed')->getContent())->toBe('removed')
        ->and(get($this->app, '/articles/removed')->getStatusCode())->toBe(404);
});

it('answers 404 for a missing model', function () {
    $this->router->get('authors/{author}', fn (Author $author) => $author->slug)->middleware(SubstituteBindings::class);

    expect(get($this->app, '/authors/99')->getStatusCode())->toBe(404);
});

it('leaves a model that binds itself in charge of its own binding', function () {
    $this->router->get('published/{article:slug}', fn (PublishedArticle $article) => $article->slug)->middleware(SubstituteBindings::class);

    expect(get($this->app, '/published/engines')->getContent())->toBe('engines')
        ->and(get($this->app, '/published/draft')->getStatusCode())->toBe(404);
});

it('binds Instrument models registered explicitly with Route::model()', function () {
    $this->router->model('writer', Author::class);
    $this->router->get('writers/{writer}', fn ($writer) => $writer->slug)->middleware(SubstituteBindings::class);

    expect(get($this->app, '/writers/1')->getContent())->toBe('ada')
        ->and(get($this->app, '/writers/99')->getStatusCode())->toBe(404);
});

it('binds explicitly with Route::bind() as before', function () {
    $this->router->bind('slugged', fn ($value) => Author::where('slug', $value)->firstOrFail());
    $this->router->get('slugged/{slugged}', fn ($slugged) => (string) $slugged->id)->middleware(SubstituteBindings::class);

    expect(get($this->app, '/slugged/grace')->getContent())->toBe('2');
});
