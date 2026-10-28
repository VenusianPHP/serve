<?php

use Mariner\Auth\Access\AuthorizationException;
use Mariner\Auth\Access\AuthorizesRequests;
use Mariner\Auth\User;
use Mariner\Contracts\Auth\Access\Gate;
use Mariner\Core\Http\Kernel;
use Mariner\Http\Request;
use Mariner\Routing\Controller;
use Venusian\Serve\Tests\Fixtures\WebApp;

class Article
{
    public function __construct(public int $author_id) {}
}

class Author extends User
{
    protected $guarded = [];
}

class ArticlePolicy
{
    public function update(Author $user, Article $article): bool
    {
        return $user->id === $article->author_id;
    }

    public function viewAny(Author $user): bool
    {
        return true;
    }
}

class ArticleController extends Controller
{
    use AuthorizesRequests;

    public function update(Article $article)
    {
        return $this->authorize($article);
    }

    public function named(string $ability, Article $article)
    {
        return $this->authorize($ability, $article);
    }
}

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->gate = $this->app->make(Gate::class);
    $this->gate->policy(Article::class, ArticlePolicy::class);
    $this->app['auth']->guard()->setUser(new Author(['id' => 1]));
});
afterEach(fn () => WebApp::tearDown($this));

it('authorizes an ability for the current user, and throws when it is denied', function () {
    $controller = new ArticleController;

    expect($controller->named('update', new Article(1))->allowed())->toBeTrue()
        ->and(fn () => $controller->named('update', new Article(2)))->toThrow(AuthorizationException::class);
});

it('guesses the ability from the calling method when given only a model', function () {
    $controller = new ArticleController;

    expect($controller->update(new Article(1))->allowed())->toBeTrue()
        ->and(fn () => $controller->update(new Article(2)))->toThrow(AuthorizationException::class);
});

it('authorizes for another user', function () {
    $controller = new ArticleController;

    expect($controller->authorizeForUser(new Author(['id' => 2]), 'update', new Article(2))->allowed())->toBeTrue()
        ->and(fn () => $controller->authorizeForUser(new Author(['id' => 3]), 'update', new Article(2)))->toThrow(AuthorizationException::class);
});

it('guards each resource method with its ability', function () {
    $controller = new ArticleController;
    $controller->authorizeResource(Article::class, 'article');

    $middleware = collect($controller->getMiddleware())->mapWithKeys(fn ($m) => [$m['middleware'] => $m['options']['only'] ?? []])->all();

    expect($middleware)->toBe([
        'can:viewAny,'.Article::class => ['index'],
        'can:view,article' => ['show'],
        'can:create,'.Article::class => ['create', 'store'],
        'can:update,article' => ['edit', 'update'],
        'can:delete,article' => ['destroy'],
    ]);
});

it('answers a denied request with 403', function () {
    $this->app['router']->get('/articles/{id}/edit', fn (int $id) => (new ArticleController)->named('update', new Article($id)) ? 'ok' : 'no');
    $kernel = $this->app->make(Kernel::class);
    config(['session.driver' => 'array']);

    expect($kernel->handle(Request::create('/articles/1/edit'))->getStatusCode())->toBe(200)
        ->and($kernel->handle(Request::create('/articles/2/edit'))->getStatusCode())->toBe(403);
});
