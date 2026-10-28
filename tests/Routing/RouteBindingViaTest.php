<?php

use Mariner\Http\Request;
use Mariner\Routing\Middleware\SubstituteBindings;
use Venusian\Serve\Tests\Fixtures\Article;
use Venusian\Serve\Tests\Fixtures\Author;
use Venusian\Serve\Tests\Fixtures\LiveArticle;
use Venusian\Serve\Tests\Fixtures\PublishedArticle;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Database\Schema\Blueprint;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->dir = sys_get_temp_dir().'/mariner-binding-'.bin2hex(random_bytes(4));
    mkdir($this->dir);

    // A database file both this process and the pool's workers open.
    touch($this->dir.'/binding.sqlite');
    $this->app['config']->set('database.connections.binding', ['driver' => 'sqlite', 'database' => $this->dir.'/binding.sqlite', 'prefix' => '']);

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

    $this->router = $this->app['router'];
});

afterEach(function () {
    $this->app['files']->deleteDirectory($this->dir);
    WebApp::tearDown($this);
});

/** Dispatches a GET through the router (with binding substitution) and returns the response. */
function getVia($app, string $uri)
{
    $request = Request::create($uri);
    $app->registerInstance('request', $request);

    return $app['router']->dispatch($request);
}

// Thread workers need ext-parallel; without it, 'auto' picks process workers.
dataset('pools', array_merge(['auto', 'process'], extension_loaded('parallel') ? ['thread'] : []));

it('runs binding queries in place when binding_via is off', function () {
    $queries = 0;
    $this->app['db']->connection('binding')->listen(function () use (&$queries) {
        $queries++;
    });
    $this->router->get('authors/{author}', fn (Author $author) => $author->slug)->middleware(SubstituteBindings::class);

    expect(getVia($this->app, '/authors/2')->getContent())->toBe('grace')
        ->and($queries)->toBe(1);
});

it('runs binding queries on the configured pool', function (string $pool) {
    $this->app['config']->set('routing.binding_via', $pool);
    $queries = 0;
    $this->app['db']->connection('binding')->listen(function () use (&$queries) {
        $queries++;
    });
    $this->router->get('authors/{author}/articles/{article:slug}', fn (Author $author, Article $article) => $author->slug.'/'.$article->slug)
        ->middleware(SubstituteBindings::class);

    expect(getVia($this->app, '/authors/1/articles/engines')->getContent())->toBe('ada/engines')
        ->and(getVia($this->app, '/authors/1/articles/compilers')->getStatusCode())->toBe(404)
        ->and($queries)->toBe(0);
})->with('pools');

it('keeps a model\'s own binding query when offloaded', function (string $pool) {
    $this->app['config']->set('routing.binding_via', $pool);
    $queries = 0;
    $this->app['db']->connection('binding')->listen(function () use (&$queries) {
        $queries++;
    });
    $this->router->get('live/{article:slug}', fn (LiveArticle $article) => $article->slug)->middleware(SubstituteBindings::class);

    expect(getVia($this->app, '/live/engines')->getContent())->toBe('engines')
        ->and(getVia($this->app, '/live/draft')->getStatusCode())->toBe(404)
        ->and($queries)->toBe(0);
})->with('pools');

it('leaves models that resolve themselves in place', function () {
    $this->app['config']->set('routing.binding_via', 'process');
    $queries = 0;
    $this->app['db']->connection('binding')->listen(function () use (&$queries) {
        $queries++;
    });
    $this->router->get('published/{article:slug}', fn (PublishedArticle $article) => $article->slug)->middleware(SubstituteBindings::class);

    expect(getVia($this->app, '/published/engines')->getContent())->toBe('engines')
        ->and($queries)->toBe(1);
});

it('surfaces a pool that is off instead of quietly running in place', function () {
    $this->app['config']->set('routing.binding_via', 'thread');
    $this->router->get('authors/{author}', fn (Author $author) => $author->slug)->middleware(SubstituteBindings::class);

    expect(getVia($this->app, '/authors/1')->getStatusCode())->toBe(500);
})->skip(extension_loaded('parallel'), 'thread workers are on');

it('lets two requests in loop tasks overlap their offloaded bindings', function () {
    $this->app['config']->set('routing.binding_via', 'auto');
    $loop = $this->app['event-loop'];
    $events = [];
    $this->router->get('authors/{author}', function (Author $author) use (&$events) {
        $events[] = "{$author->id}:bound";

        return $author->slug;
    })->middleware(SubstituteBindings::class);

    $request = function (int $id) use ($loop, &$events) {
        return $loop->async(function () use ($id, &$events) {
            $events[] = "{$id}:start";

            return getVia($this->app, "/authors/{$id}")->getContent();
        });
    };

    $a = $request(1);
    $b = $request(2);

    expect([$loop->await($a), $loop->await($b)])->toBe(['ada', 'grace'])
        ->and(array_search('2:start', $events))->toBeLessThan(array_search('1:bound', $events));
});
