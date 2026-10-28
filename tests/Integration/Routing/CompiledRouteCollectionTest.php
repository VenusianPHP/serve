<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use ArrayIterator;
use Mariner\Http\Request;
use Mariner\Routing\Route;
use Mariner\Routing\RouteCollection;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {

    $this->router = $this->app['router'];

    $this->routeCollection = new RouteCollection;
});

afterEach(function () {
    unset($this->routeCollection, $this->router);

});

function collection($test)
{
    return $test->routeCollection->toCompiledRouteCollection($test->router, app());
}

test('route collection can add route', function () {
    $this->routeCollection->add(newRoute($this, 'GET', 'foo', [
        'uses' => 'FooController@index',
        'as' => 'foo_index',
    ]));

    expect(collection($this))->toHaveCount(1);
});

test('route collection add returns the route', function () {
    $outputRoute = collection($this)->add($inputRoute = newRoute($this, 'GET', 'foo', [
        'uses' => 'FooController@index',
        'as' => 'foo_index',
    ]));

    expect($outputRoute)->toBeInstanceOf(Route::class);
    expect($outputRoute)->toEqual($inputRoute);
});

test('route collection can retrieve by name', function () {
    $this->routeCollection->add($routeIndex = newRoute($this, 'GET', 'foo/index', [
        'uses' => 'FooController@index',
        'as' => 'route_name',
    ]));

    $routes = collection($this);

    expect($routeIndex->getName())->toBe('route_name');
    expect($routes->getByName('route_name')->getName())->toBe('route_name');
    expect($routes->getByName('route_name'))->toEqual($routeIndex);
});

test('route collection can retrieve by action', function () {
    $this->routeCollection->add($routeIndex = newRoute($this, 'GET', 'foo/index', $action = [
        'uses' => 'FooController@index',
    ]));

    $route = collection($this)->getByAction('FooController@index');

    expect(Arr::except($routeIndex->getAction(), 'as'))->toBe($action);
    expect(Arr::except($route->getAction(), 'as'))->toBe($action);
});

test('compiled and non compiled url resolution has same precedence for actions', function () {
    $this->router->get('/foo/{bar}', ['FooController', 'show']);
    $this->router->get('/foo/{bar}/{baz}', ['FooController', 'show']);
    $this->router->getRoutes()->refreshActionLookups();

    expect($this->router->getRoutes()->getByAction('FooController@show')->uri)->toBe('foo/{bar}');

    // The same two routes, now from the route cache that route:cache writes.
    $this->defineCacheRoutes(<<<'PHP'
<?php

use Mariner\NutsAndBolts\Facades\Route;

Route::get('/foo/{bar}', ['FooController', 'show']);
Route::get('/foo/{bar}/{baz}', ['FooController', 'show']);
PHP);

    expect($this->app['router']->getRoutes()->getByAction('FooController@show')->uri)->toBe('foo/{bar}');
});

test('compiled and non compiled url resolution has same precedence for names', function () {
    $this->router->get('/foo/{bar}', ['FooController', 'show'])->name('foo.show');
    $this->router->get('/foo/{bar}/{baz}', ['FooController', 'show'])->name('foo.show');
    $this->router->getRoutes()->refreshNameLookups();

    expect($this->router->getRoutes()->getByName('foo.show')->uri)->toBe('foo/{bar}');
});

test('route collection can get iterator', function () {
    $this->routeCollection->add(newRoute($this, 'GET', 'foo/index', [
        'uses' => 'FooController@index',
        'as' => 'foo_index',
    ]));

    expect(collection($this)->getIterator())->toBeInstanceOf(ArrayIterator::class);
});

test('route collection can get iterator when empty', function () {
    $routes = collection($this);

    expect($routes)->toHaveCount(0);
    expect($routes->getIterator())->toBeInstanceOf(ArrayIterator::class);
});

test('route collection can get iterator when routes are added', function () {
    $this->routeCollection->add(newRoute($this, 'GET', 'foo/index', [
        'uses' => 'FooController@index',
        'as' => 'foo_index',
    ]));

    $routes = collection($this);

    expect($routes)->toHaveCount(1);

    $this->routeCollection->add(newRoute($this, 'GET', 'bar/show', [
        'uses' => 'BarController@show',
        'as' => 'bar_show',
    ]));

    $routes = collection($this);

    expect($routes)->toHaveCount(2);

    expect($routes->getIterator())->toBeInstanceOf(ArrayIterator::class);
});

test('route collection can handle same route', function () {
    $this->routeCollection->add($routeIndex = newRoute($this, 'GET', 'foo/index', [
        'uses' => 'FooController@index',
        'as' => 'foo_index',
    ]));

    $routes = collection($this);

    expect($routes)->toHaveCount(1);

    // Add exactly the same route
    $this->routeCollection->add($routeIndex);

    $routes = collection($this);

    expect($routes)->toHaveCount(1);

    // Add a non-existing route
    $this->routeCollection->add(newRoute($this, 'GET', 'bar/show', [
        'uses' => 'BarController@show',
        'as' => 'bar_show',
    ]));

    $routes = collection($this);

    expect($routes)->toHaveCount(2);
});

test('route collection can get all routes', function () {
    $this->routeCollection->add($routeIndex = newRoute($this, 'GET', 'foo/index', [
        'uses' => 'FooController@index',
        'as' => 'foo_index',
    ]));
    $this->routeCollection->add($routeShow = newRoute($this, 'GET', 'foo/show', [
        'uses' => 'FooController@show',
        'as' => 'foo_show',
    ]));
    $this->routeCollection->add($routeNew = newRoute($this, 'POST', 'bar', [
        'uses' => 'BarController@create',
        'as' => 'bar_create',
    ]));

    $allRoutes = [
        $routeIndex,
        $routeShow,
        $routeNew,
    ];
    expect(collection($this)->getRoutes())->toEqual($allRoutes);
});

test('route collection can get routes by name', function () {
    $routesByName = [
        'foo_index' => newRoute($this, 'GET', 'foo/index', [
            'uses' => 'FooController@index',
            'as' => 'foo_index',
        ]),
        'foo_show' => newRoute($this, 'GET', 'foo/show', [
            'uses' => 'FooController@show',
            'as' => 'foo_show',
        ]),
        'bar_create' => newRoute($this, 'POST', 'bar', [
            'uses' => 'BarController@create',
            'as' => 'bar_create',
        ]),
    ];

    $this->routeCollection->add($routesByName['foo_index']);
    $this->routeCollection->add($routesByName['foo_show']);
    $this->routeCollection->add($routesByName['bar_create']);

    expect(collection($this)->getRoutesByName())->toEqual($routesByName);
});

test('route collection can get routes by method', function () {
    $routes = [
        'foo_index' => newRoute($this, 'GET', 'foo/index', [
            'uses' => 'FooController@index',
            'as' => 'foo_index',
        ]),
        'foo_show' => newRoute($this, 'GET', 'foo/show', [
            'uses' => 'FooController@show',
            'as' => 'foo_show',
        ]),
        'bar_create' => newRoute($this, 'POST', 'bar', [
            'uses' => 'BarController@create',
            'as' => 'bar_create',
        ]),
    ];

    $this->routeCollection->add($routes['foo_index']);
    $this->routeCollection->add($routes['foo_show']);
    $this->routeCollection->add($routes['bar_create']);

    expect(collection($this)->getRoutesByMethod())->toEqual([
        'GET' => [
            'foo/index' => $routes['foo_index'],
            'foo/show' => $routes['foo_show'],
        ],
        'HEAD' => [
            'foo/index' => $routes['foo_index'],
            'foo/show' => $routes['foo_show'],
        ],
        'POST' => [
            'bar' => $routes['bar_create'],
        ],
    ]);
});

test('route collection cleans up overwritten routes', function () {
    // Create two routes with the same path and method.
    $routeA = newRoute($this, 'GET', 'product', ['controller' => 'View@view', 'as' => 'routeA']);
    $routeB = newRoute($this, 'GET', 'product', ['controller' => 'OverwrittenView@view', 'as' => 'overwrittenRouteA']);

    $this->routeCollection->add($routeA);
    $this->routeCollection->add($routeB);

    // Check if the lookups of $routeA and $routeB are there.
    expect($this->routeCollection->getByName('routeA'))->toEqual($routeA);
    expect($this->routeCollection->getByAction('View@view'))->toEqual($routeA);
    expect($this->routeCollection->getByName('overwrittenRouteA'))->toEqual($routeB);
    expect($this->routeCollection->getByAction('OverwrittenView@view'))->toEqual($routeB);

    $routes = collection($this);

    // The lookups of $routeA should not be there anymore, because they are no longer valid.
    expect($routes->getByName('routeA'))->toBeNull();
    expect($routes->getByAction('View@view'))->toBeNull();
    // The lookups of $routeB are still there.
    expect($routes->getByName('overwrittenRouteA'))->toEqual($routeB);
    expect($routes->getByAction('OverwrittenView@view'))->toEqual($routeB);
});

test('matching throws not found exception when route is not found', function () {
    $this->routeCollection->add(newRoute($this, 'GET', '/', ['uses' => 'FooController@index']));

    collection($this)->match(Request::create('/foo'));
})->throws(NotFoundHttpException::class);

test('matching respects https only routes', function () {
    $this->routeCollection->add(newRoute($this, 'GET', 'account', ['https', 'uses' => 'FooController@index', 'as' => 'account']));

    $routes = collection($this);

    expect($routes->match(Request::create('https://localhost/account'))->getName())->toBe('account');

    $routes->match(Request::create('http://localhost/account'));
})->throws(NotFoundHttpException::class);

test('matching respects http only routes', function () {
    $this->routeCollection->add(newRoute($this, 'GET', 'account', ['http', 'uses' => 'FooController@index', 'as' => 'account']));

    $routes = collection($this);

    expect($routes->match(Request::create('http://localhost/account'))->getName())->toBe('account');

    $routes->match(Request::create('https://localhost/account'));
})->throws(NotFoundHttpException::class);

test('matching throws method not allowed http exception when method is not allowed', function () {
    $this->routeCollection->add(newRoute($this, 'GET', '/foo', ['uses' => 'FooController@index']));

    collection($this)->match(Request::create('/foo', 'POST'));
})->throws(MethodNotAllowedHttpException::class);

test('matching throws exception when method is not allowed while same route is added dynamically', function () {
    $this->routeCollection->add(newRoute($this, 'GET', '/', ['uses' => 'FooController@index']));

    $routes = collection($this);

    $routes->add(newRoute($this, 'POST', '/', ['uses' => 'FooController@index']));

    $routes->match(Request::create('/', 'PUT'));
})->throws(MethodNotAllowedHttpException::class);

test('matching route with same dynamically added route always matches cached one first', function () {
    $this->routeCollection->add(
        $route = newRoute($this, 'GET', '/', ['uses' => 'FooController@index', 'as' => 'foo'])
    );

    $routes = collection($this);

    $routes->add(newRoute($this, 'GET', '/', ['uses' => 'FooController@index', 'as' => 'bar']));

    expect($routes->match(Request::create('/', 'GET'))->getName())->toBe('foo');
});

test('matching finds route with different method dynamically', function () {
    $this->routeCollection->add(newRoute($this, 'GET', '/foo', ['uses' => 'FooController@index']));

    $routes = collection($this);

    $routes->add($route = newRoute($this, 'POST', '/foo', ['uses' => 'FooController@index']));

    // Mariner matches a per-request copy of the route (Route::forRequest()), so the route is compared by what it is.
    $matched = $routes->match(Request::create('/foo', 'POST'));

    expect($matched->methods())->toBe($route->methods())
        ->and($matched->uri())->toBe($route->uri())
        ->and($matched->getAction())->toBe($route->getAction());
});

test('matching wildcard from compiled routes always takes precedent', function () {
    $this->routeCollection->add(
        $route = newRoute($this, 'GET', '{wildcard}', ['uses' => 'FooController@index', 'as' => 'foo'])
            ->where('wildcard', '.*')
    );

    $routes = collection($this);

    $routes->add(
        newRoute($this, 'GET', '{wildcard}', ['uses' => 'FooController@index', 'as' => 'bar'])
            ->where('wildcard', '.*')
    );

    expect($routes->match(Request::create('/foo', 'GET'))->getName())->toBe('foo');
});

test('matching dynamically added routes take precedence over fallback routes', function () {
    $this->routeCollection->add(fallbackRoute($this, ['uses' => 'FooController@index']));
    $this->routeCollection->add(
        newRoute($this, 'GET', '/foo/{id}', ['uses' => 'FooController@index', 'as' => 'foo'])
    );

    $routes = collection($this);

    $routes->add(newRoute($this, 'GET', '/bar/{id}', ['uses' => 'FooController@index', 'as' => 'bar']));

    expect($routes->match(Request::create('/bar/1', 'GET'))->getName())->toBe('bar');
});

test('matching fallback route catches all', function () {
    $this->routeCollection->add(fallbackRoute($this, ['uses' => 'FooController@index', 'as' => 'fallback']));
    $this->routeCollection->add(
        newRoute($this, 'GET', '/foo/{id}', ['uses' => 'FooController@index', 'as' => 'foo'])
    );

    $routes = collection($this);

    $routes->add(newRoute($this, 'GET', '/bar/{id}', ['uses' => 'FooController@index', 'as' => 'bar']));

    expect($routes->match(Request::create('/baz/1', 'GET'))->getName())->toBe('fallback');
});

test('matching cached fallback takes precedence over dynamic fallback', function () {
    $this->routeCollection->add(fallbackRoute($this, ['uses' => 'FooController@index', 'as' => 'fallback']));

    $routes = collection($this);

    $routes->add(fallbackRoute($this, ['uses' => 'FooController@index', 'as' => 'dynamic_fallback']));

    expect($routes->match(Request::create('/baz/1', 'GET'))->getName())->toBe('fallback');
});

test('matching cached fallback takes precedence over dynamic route with wrong method', function () {
    $this->routeCollection->add(fallbackRoute($this, ['uses' => 'FooController@index', 'as' => 'fallback']));

    $routes = collection($this);

    $routes->add(newRoute($this, 'POST', '/bar/{id}', ['uses' => 'FooController@index', 'as' => 'bar']));

    expect($routes->match(Request::create('/bar/1', 'GET'))->getName())->toBe('fallback');
});

test('slash prefix is properly handled', function () {
    $this->routeCollection->add(newRoute($this, 'GET', 'foo/bar', ['uses' => 'FooController@index', 'prefix' => '/']));

    $route = collection($this)->getByAction('FooController@index');

    expect($route->uri())->toBe('foo/bar');
});

test('route without namespace is found', function () {
    $this->routeCollection->add(newRoute($this, 'GET', 'foo/bar', ['controller' => '\App\FooController']));

    $route = collection($this)->getByAction('App\FooController');

    expect($route->uri())->toBe('foo/bar');
});

test('group prefix and route prefix are properly handled', function () {
    $this->routeCollection->add(newRoute($this, 'GET', 'foo/bar', ['uses' => 'FooController@index', 'prefix' => '{locale}'])->prefix('pre'));

    $route = collection($this)->getByAction('FooController@index');

    expect($route->getPrefix())->toBe('pre/{locale}');
});

test('group generate name for duplicate route names that end with dot', function () {
    $this->routeCollection->add(newRoute($this, 'GET', 'foo', ['uses' => 'FooController@index'])->name('foo.'));
    $this->routeCollection->add($route = newRoute($this, 'GET', 'bar', ['uses' => 'BarController@index'])->name('foo.'));

    $routes = collection($this);

    expect($routes->match(Request::create('/bar', 'GET'))->getAction()['uses'])->toBe('BarController@index');
});

test('route bindings are properly saved', function () {
    $this->routeCollection->add(newRoute($this, 'GET', 'posts/{post:slug}/show', [
        'uses' => 'FooController@index',
        'prefix' => 'profile/{user:username}',
        'as' => 'foo',
    ]));

    $route = collection($this)->getByName('foo');

    expect($route->uri())->toBe('profile/{user}/posts/{post}/show');
    expect($route->bindingFields())->toBe(['user' => 'username', 'post' => 'slug']);
});

test('matching slashed routes', function () {
    $this->routeCollection->add(
        $route = newRoute($this, 'GET', 'foo/bar', ['uses' => 'FooController@index', 'as' => 'foo'])
    );

    expect(collection($this)->match(Request::create('/foo/bar/'))->getName())->toBe('foo');
});

test('matching uri with query', function () {
    $this->routeCollection->add(
        $route = newRoute($this, 'GET', 'foo/bar', ['uses' => 'FooController@index', 'as' => 'foo'])
    );

    expect(collection($this)->match(Request::create('/foo/bar/?foo=bar'))->getName())->toBe('foo');
});

test('matching root uri', function () {
    $this->routeCollection->add(
        $route = newRoute($this, 'GET', '/', ['uses' => 'FooController@index', 'as' => 'foo'])
    );

    expect(collection($this)->match(Request::create('http://example.com'))->getName())->toBe('foo');
});

test('trailing slash is trimmed when matching cached routes', function () {
    $this->routeCollection->add(
        newRoute($this, 'GET', 'foo/bar', ['uses' => 'FooController@index', 'as' => 'foo'])
    );

    $request = Request::create('/foo/bar/');

    // Access to request path info before matching route
    $request->getPathInfo();

    expect(collection($this)->match($request)->getName())->toBe('foo');
});

test('matching root uri when served from subdirectory', function () {
    $this->routeCollection->add(
        newRoute($this, 'GET', '/', ['uses' => 'FooController@index', 'as' => 'foo'])
    );

    $request = Request::create('http://example.com/app/', 'GET', [], [], [], [
        'SCRIPT_NAME' => '/app/index.php',
        'PHP_SELF' => '/app/index.php',
        'SCRIPT_FILENAME' => '/var/www/public/index.php',
    ]);

    expect(collection($this)->match($request)->getName())->toBe('foo');
});

test('matching root uri with repeated slashes', function () {
    $this->routeCollection->add(
        newRoute($this, 'GET', '/', ['uses' => 'FooController@index', 'as' => 'foo'])
    );

    expect(collection($this)->match(Request::create('http://example.com//'))->getName())->toBe('foo');

    $request = Request::create('http://example.com/app//', 'GET', [], [], [], [
        'SCRIPT_NAME' => '/app/index.php',
        'PHP_SELF' => '/app/index.php',
        'SCRIPT_FILENAME' => '/var/www/public/index.php',
    ]);

    expect(collection($this)->match($request)->getName())->toBe('foo');
});

test('matching uri when served from subdirectory', function () {
    $this->routeCollection->add(
        newRoute($this, 'GET', 'foo/bar', ['uses' => 'FooController@index', 'as' => 'foo'])
    );

    $request = Request::create('http://example.com/app/foo/bar/', 'GET', [], [], [], [
        'SCRIPT_NAME' => '/app/index.php',
        'PHP_SELF' => '/app/index.php',
        'SCRIPT_FILENAME' => '/var/www/public/index.php',
    ]);

    expect(collection($this)->match($request)->getName())->toBe('foo');
});

test('route with same path and same method but diff domain name with options method', function () {
    $routes = [
        'foo_domain' => newRoute($this, 'GET', 'same/path', [
            'uses' => 'FooController@index',
            'as' => 'foo',
            'domain' => 'foo.localhost',
        ]),
        'bar_domain' => newRoute($this, 'GET', 'same/path', [
            'uses' => 'BarController@index',
            'as' => 'bar',
            'domain' => 'bar.localhost',
        ]),
        'no_domain' => newRoute($this, 'GET', 'same/path', [
            'uses' => 'BarController@index',
            'as' => 'no_domain',
        ]),
    ];

    $this->routeCollection->add($routes['foo_domain']);
    $this->routeCollection->add($routes['bar_domain']);
    $this->routeCollection->add($routes['no_domain']);

    $expectedMethods = [
        'OPTIONS',
    ];

    expect(collection($this)->match(
        Request::create('http://foo.localhost/same/path', 'OPTIONS')
    )->methods)->toBe($expectedMethods);

    expect(collection($this)->match(
        Request::create('http://bar.localhost/same/path', 'OPTIONS')
    )->methods)->toBe($expectedMethods);

    expect(collection($this)->match(
        Request::create('http://no.localhost/same/path', 'OPTIONS')
    )->methods)->toBe($expectedMethods);

    expect(collection($this)->getRoutesByMethod())->toEqual([
        'HEAD' => [
            'foo.localhostsame/path' => $routes['foo_domain'],
            'bar.localhostsame/path' => $routes['bar_domain'],
            'same/path' => $routes['no_domain'],
        ],
        'GET' => [
            'foo.localhostsame/path' => $routes['foo_domain'],
            'bar.localhostsame/path' => $routes['bar_domain'],
            'same/path' => $routes['no_domain'],
        ],
    ]);
});

function newRoute($test, $methods, $uri, $action)
{
    return (new Route($methods, $uri, $action))
        ->setRouter($test->router)
        ->setContainer(app());
}

function fallbackRoute($test, $action)
{
    $placeholder = 'fallbackPlaceholder';

    return newRoute($test,
        'GET', "{{$placeholder}}", $action
    )->where($placeholder, '.*')->fallback();
}
