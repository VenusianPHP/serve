<?php

namespace Venusian\Serve\Tests\Routing;

use ArrayIterator;
use Voyager\Vessel\ControlPanel;
use Voyager\Signals\SignalDispatcher;
use Mariner\Http\Request;
use Mariner\Routing\CompiledRouteCollection;
use Mariner\Routing\Route;
use Mariner\Routing\RouteCollection;
use Mariner\Routing\Router;
use LogicException;
use Symfony\Component\HttpKernel\Exception\MethodNotAllowedHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

beforeEach(function () {
    $this->routeCollection = new RouteCollection;
});

test('route collection can add route', function () {
    $this->routeCollection->add(new Route('GET', 'foo', [
        'uses' => 'FooController@index',
        'as' => 'foo_index',
    ]));
    expect($this->routeCollection)->toHaveCount(1);
});

test('route collection add returns the route', function () {
    $outputRoute = $this->routeCollection->add($inputRoute = new Route('GET', 'foo', [
        'uses' => 'FooController@index',
        'as' => 'foo_index',
    ]));
    expect($outputRoute)->toBeInstanceOf(Route::class);
    expect($outputRoute)->toEqual($inputRoute);
});

test('route collection can retrieve by name', function () {
    $this->routeCollection->add($routeIndex = new Route('GET', 'foo/index', [
        'uses' => 'FooController@index',
        'as' => 'route_name',
    ]));

    expect($routeIndex->getName())->toBe('route_name');
    expect($this->routeCollection->getByName('route_name')->getName())->toBe('route_name');
    expect($this->routeCollection->getByName('route_name'))->toEqual($routeIndex);
});

test('route collection can retrieve by action', function () {
    $this->routeCollection->add($routeIndex = new Route('GET', 'foo/index', $action = [
        'uses' => 'FooController@index',
        'as' => 'route_name',
    ]));

    expect($routeIndex->getAction())->toBe($action);
});

test('route collection can retrieve by method', function () {
    $this->routeCollection->add($routeIndex = new Route('GET', 'foo/index', $action = [
        'uses' => 'FooController@index',
        'as' => 'route_name',
    ]));

    expect($this->routeCollection->get('GET'))->toHaveCount(1);
    expect($this->routeCollection->get('GET.foo/index'))->toHaveCount(0);
    expect($this->routeCollection->get('GET')['foo/index'])->toBe($routeIndex);

    $this->routeCollection->add($routeShow = new Route('GET', 'bar/show', [
        'uses' => 'BarController@show',
        'as' => 'bar_show',
    ]));
    expect($this->routeCollection->get('GET'))->toHaveCount(2);
});

test('route collection can get iterator', function () {
    $this->routeCollection->add(new Route('GET', 'foo/index', [
        'uses' => 'FooController@index',
        'as' => 'foo_index',
    ]));
    expect($this->routeCollection->getIterator())->toBeInstanceOf(ArrayIterator::class);
});

test('route collection can get iterator when empty', function () {
    expect($this->routeCollection)->toHaveCount(0);
    expect($this->routeCollection->getIterator())->toBeInstanceOf(ArrayIterator::class);
});

test('route collection can get iterator when route are added', function () {
    $this->routeCollection->add($routeIndex = new Route('GET', 'foo/index', [
        'uses' => 'FooController@index',
        'as' => 'foo_index',
    ]));
    expect($this->routeCollection)->toHaveCount(1);

    $this->routeCollection->add($routeShow = new Route('GET', 'bar/show', [
        'uses' => 'BarController@show',
        'as' => 'bar_show',
    ]));
    expect($this->routeCollection)->toHaveCount(2);

    expect($this->routeCollection->getIterator())->toBeInstanceOf(ArrayIterator::class);
});

test('route collection can handle same route', function () {
    $routeIndex = new Route('GET', 'foo/index', [
        'uses' => 'FooController@index',
        'as' => 'foo_index',
    ]);

    $this->routeCollection->add($routeIndex);
    expect($this->routeCollection)->toHaveCount(1);

    // Add exactly the same route
    $this->routeCollection->add($routeIndex);
    expect($this->routeCollection)->toHaveCount(1);

    // Add a non-existing route
    $this->routeCollection->add(new Route('GET', 'bar/show', [
        'uses' => 'BarController@show',
        'as' => 'bar_show',
    ]));
    expect($this->routeCollection)->toHaveCount(2);
});

test('route collection can refresh name lookups', function () {
    $routeIndex = new Route('GET', 'foo/index', [
        'uses' => 'FooController@index',
    ]);

    // The name of the route is not yet set. It will be while adding if to the RouteCollection.
    expect($routeIndex->getName())->toBeNull();

    // The route name is set by calling \Mariner\Routing\Route::name()
    $this->routeCollection->add($routeIndex)->name('route_name');

    // No route is found. This is normal, as no refresh as been done.
    expect($this->routeCollection->getByName('route_name'))->toBeNull();

    // After the refresh, the name will be properly set to the route.
    $this->routeCollection->refreshNameLookups();
    expect($this->routeCollection->getByName('route_name'))->toEqual($routeIndex);
});

test('route collection can get all routes', function () {
    $this->routeCollection->add($routeIndex = new Route('GET', 'foo/index', [
        'uses' => 'FooController@index',
        'as' => 'foo_index',
    ]));

    $this->routeCollection->add($routeShow = new Route('GET', 'foo/show', [
        'uses' => 'FooController@show',
        'as' => 'foo_show',
    ]));

    $this->routeCollection->add($routeNew = new Route('POST', 'bar', [
        'uses' => 'BarController@create',
        'as' => 'bar_create',
    ]));

    $allRoutes = [
        $routeIndex,
        $routeShow,
        $routeNew,
    ];
    expect($this->routeCollection->getRoutes())->toEqual($allRoutes);
});

test('route collection can get routes by name', function () {
    $routesByName = [
        'foo_index' => new Route('GET', 'foo/index', [
            'uses' => 'FooController@index',
            'as' => 'foo_index',
        ]),
        'foo_show' => new Route('GET', 'foo/show', [
            'uses' => 'FooController@show',
            'as' => 'foo_show',
        ]),
        'bar_create' => new Route('POST', 'bar', [
            'uses' => 'BarController@create',
            'as' => 'bar_create',
        ]),
    ];

    $this->routeCollection->add($routesByName['foo_index']);
    $this->routeCollection->add($routesByName['foo_show']);
    $this->routeCollection->add($routesByName['bar_create']);

    expect($this->routeCollection->getRoutesByName())->toBe($routesByName);
});

test('route collection can get routes by method', function () {
    $routes = [
        'foo_index' => new Route('GET', 'foo/index', [
            'uses' => 'FooController@index',
            'as' => 'foo_index',
        ]),
        'foo_show' => new Route('GET', 'foo/show', [
            'uses' => 'FooController@show',
            'as' => 'foo_show',
        ]),
        'bar_create' => new Route('POST', 'bar', [
            'uses' => 'BarController@create',
            'as' => 'bar_create',
        ]),
    ];

    $this->routeCollection->add($routes['foo_index']);
    $this->routeCollection->add($routes['foo_show']);
    $this->routeCollection->add($routes['bar_create']);

    expect($this->routeCollection->getRoutesByMethod())->toBe([
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
    $routeA = new Route('GET', 'product', ['controller' => 'View@view', 'as' => 'routeA']);
    $routeB = new Route('GET', 'product', ['controller' => 'OverwrittenView@view', 'as' => 'overwrittenRouteA']);

    $this->routeCollection->add($routeA);
    $this->routeCollection->add($routeB);

    // Check if the lookups of $routeA and $routeB are there.
    expect($this->routeCollection->getByName('routeA'))->toEqual($routeA);
    expect($this->routeCollection->getByAction('View@view'))->toEqual($routeA);
    expect($this->routeCollection->getByName('overwrittenRouteA'))->toEqual($routeB);
    expect($this->routeCollection->getByAction('OverwrittenView@view'))->toEqual($routeB);

    // Rebuild the lookup arrays.
    $this->routeCollection->refreshNameLookups();
    $this->routeCollection->refreshActionLookups();

    // The lookups of $routeA should not be there anymore, because they are no longer valid.
    expect($this->routeCollection->getByName('routeA'))->toBeNull();
    expect($this->routeCollection->getByAction('View@view'))->toBeNull();
    // The lookups of $routeB are still there.
    expect($this->routeCollection->getByName('overwrittenRouteA'))->toEqual($routeB);
    expect($this->routeCollection->getByAction('OverwrittenView@view'))->toEqual($routeB);
});

test('cannot cache duplicate route names', function () {
    $this->routeCollection->add(
        new Route('GET', 'users', ['uses' => 'UsersController@index', 'as' => 'users'])
    );
    $this->routeCollection->add(
        new Route('GET', 'users/{user}', ['uses' => 'UsersController@show', 'as' => 'users'])
    );

    $this->routeCollection->compile();
})->throws(LogicException::class);

test('compiled route collection preserves route metadata', function () {
    $this->routeCollection->add(
        new Route('GET', 'users', [
            'uses' => 'UsersController@index',
            'as' => 'users',
            'metadata' => ['head' => ['title' => 'Users']],
        ])
    );

    $route = $this->routeCollection
        ->toCompiledRouteCollection(new Router(new SignalDispatcher, new ControlPanel), new ControlPanel)
        ->getByName('users');

    expect($route->getMetadata('head'))->toBe(['title' => 'Users']);
});

test('route collection dont match non matching double slashes', function () {

    $this->routeCollection->add(new Route('GET', 'foo', [
        'uses' => 'FooController@index',
        'as' => 'foo_index',
    ]));

    $request = Request::create('', 'GET');
    // We have to set uri in REQUEST_URI otherwise Request uses parse_url() which trim the slashes
    $request->server->set(
        'REQUEST_URI', '//foo'
    );
    $this->routeCollection->match($request);
})->throws(NotFoundHttpException::class, 'The route foo could not be found.');

test('route collection request method not allowed', function () {

    $this->routeCollection->add(
        new Route('GET', 'users', ['uses' => 'UsersController@index', 'as' => 'users'])
    );

    $request = Request::create('users', 'POST');

    expect(fn () => $this->routeCollection->match($request))->toThrow(function (MethodNotAllowedHttpException $e) {
        expect($e->getMessage())->toBe('The POST method is not supported for route users. Supported methods: GET, HEAD.')
            ->and($e->getHeaders())->toBe(['Allow' => 'GET, HEAD']);
    });
});

test('has name route method', function () {
    $this->routeCollection->add(
        new Route('GET', 'users', ['uses' => 'UsersController@index', 'as' => 'users'])
    );
    $this->routeCollection->add(
        new Route('GET', 'posts/{post}', ['uses' => 'PostController@show', 'as' => 'posts'])
    );

    $this->routeCollection->add(
        new Route('GET', 'books/{book}', ['uses' => 'BookController@show'])
    );

    expect($this->routeCollection->hasNamedRoute('users'))->toBeTrue();
    expect($this->routeCollection->hasNamedRoute('posts'))->toBeTrue();
    expect($this->routeCollection->hasNamedRoute('article'))->toBeFalse();
    expect($this->routeCollection->hasNamedRoute('books'))->toBeFalse();
});

test('to symfony route collection', function () {
    $this->routeCollection->add(
        new Route('GET', 'users', ['uses' => 'UsersController@index', 'as' => 'users'])
    );

    expect($this->routeCollection->toSymfonyRouteCollection())->toBeInstanceOf("\Symfony\Component\Routing\RouteCollection");
});

test('overlapping routes matches first route', function () {
    $this->routeCollection->add(
        new Route('GET', 'users/{id}/{other}', ['uses' => 'UsersController@other', 'as' => 'first'])
    );

    $this->routeCollection->add(
        new Route('GET', 'users/{id}/show', ['uses' => 'UsersController@show', 'as' => 'second'])
    );

    $request = Request::create('users/1/show', 'GET');

    expect($this->routeCollection->getRoutes())->toHaveCount(2);
    expect($this->routeCollection->match($request)->getName())->toBe('first');
});

test('prepends routes with domain', function () {
    $this->routeCollection->add(
        $noDomainGet1 = new Route('GET', 'no-domain-get1', ['uses' => 'NoDomainController@index'])
    );

    $this->routeCollection->add(
        $fooDomainGet1 = (new Route('GET', 'foo-domain-get1', ['uses' => 'FooDomainController@index']))->domain('foo.test')
    );

    $this->routeCollection->add(
        $barDomainGet1 = (new Route('GET', 'bar-domain-get1', ['uses' => 'BarDomainController@index']))->domain('bar.test')
    );

    $this->routeCollection->add(
        $noDomainGet2 = new Route('GET', 'no-domain-get2', ['uses' => 'NoDomainController@show'])
    );

    $this->routeCollection->add(
        $fooDomainGet2 = (new Route('GET', 'foo-domain-get2', ['uses' => 'FooDomainController@show']))->domain('foo.test')
    );

    $this->routeCollection->add(
        $barDomainGet2 = (new Route('GET', 'bar-domain-get2', ['uses' => 'BarDomainController@show']))->domain('bar.test')
    );

    expect($this->routeCollection->getRoutes())->toBe([
        $fooDomainGet1,
        $barDomainGet1,
        $fooDomainGet2,
        $barDomainGet2,
        $noDomainGet1,
        $noDomainGet2,
    ]);

    expect($this->routeCollection->getRoutesByMethod())->toBe([
        'GET' => [
            'foo.testfoo-domain-get1' => $fooDomainGet1,
            'bar.testbar-domain-get1' => $barDomainGet1,

            'foo.testfoo-domain-get2' => $fooDomainGet2,
            'bar.testbar-domain-get2' => $barDomainGet2,

            'no-domain-get1' => $noDomainGet1,
            'no-domain-get2' => $noDomainGet2,
        ],

        'HEAD' => [
            'foo.testfoo-domain-get1' => $fooDomainGet1,
            'bar.testbar-domain-get1' => $barDomainGet1,

            'foo.testfoo-domain-get2' => $fooDomainGet2,
            'bar.testbar-domain-get2' => $barDomainGet2,

            'no-domain-get1' => $noDomainGet1,
            'no-domain-get2' => $noDomainGet2,
        ],
    ]);
});

test('domain routes are matched before non domain routes', function () {
    $this->routeCollection->add(
        (new Route('GET', 'users', ['uses' => 'NoDomainController@index']))->name('no-domain')
    );

    $this->routeCollection->add(
        (new Route('GET', 'users', ['uses' => 'DomainController@index']))->domain('api.test')->name('with-domain')
    );

    $request = Request::create('http://api.test/users', 'GET');

    expect($this->routeCollection->match($request)->getName())->toBe('with-domain');
});

test('compiled route collection get returns all routes when method is null', function () {
    $this->routeCollection->add(
        new Route('GET', 'users', ['uses' => 'UsersController@index', 'as' => 'users.index'])
    );
    $this->routeCollection->add(
        new Route('POST', 'users', ['uses' => 'UsersController@store', 'as' => 'users.store'])
    );

    $compiled = toCompiledRouteCollection($this->routeCollection);

    expect($compiled->get())->toHaveCount(2);
    expect($compiled->getRoutes())->toHaveCount(2);
});

test('compiled route collection can retrieve by method', function () {
    $this->routeCollection->add(new Route('GET', 'foo/index', [
        'uses' => 'FooController@index',
        'as' => 'route_name',
    ]));

    $compiled = toCompiledRouteCollection($this->routeCollection);

    expect($compiled->get('GET'))->toHaveCount(1);
    expect($compiled->get('PUT'))->toHaveCount(0);
    expect($compiled->get('GET')['foo/index']->getName())->toBe('route_name');

    $this->routeCollection->add(new Route('GET', 'bar/show', [
        'uses' => 'BarController@show',
        'as' => 'bar_show',
    ]));

    $compiled = toCompiledRouteCollection($this->routeCollection);

    expect($compiled->get('GET'))->toHaveCount(2);
});

test('compiled route collection can retrieve by action', function () {
    $this->routeCollection->add(
        new Route('GET', 'foo/index', ['controller' => 'FooController@index', 'as' => 'foo_index'])
    );

    $compiled = toCompiledRouteCollection($this->routeCollection);

    $route = $compiled->getByAction('FooController@index');

    expect($route)->not->toBeNull();
    expect($route->getName())->toBe('foo_index');

    expect($compiled->getByAction('Missing@action'))->toBeNull();
});

test('compiled route collection action lookup prefers first registered route', function () {
    $this->routeCollection->add(
        new Route('GET', 'first', ['controller' => 'FooController@index', 'as' => 'first_route'])
    );
    $this->routeCollection->add(
        new Route('GET', 'second', ['controller' => 'FooController@index', 'as' => 'second_route'])
    );

    $route = toCompiledRouteCollection($this->routeCollection)->getByAction('FooController@index');

    expect($route->getName())->toBe('first_route');
});

test('compiled route collection can get routes by method', function () {
    $this->routeCollection->add(
        new Route('GET', 'foo/index', ['uses' => 'FooController@index', 'as' => 'foo_index'])
    );
    $this->routeCollection->add(
        new Route('GET', 'foo/show', ['uses' => 'FooController@show', 'as' => 'foo_show'])
    );
    $this->routeCollection->add(
        new Route('POST', 'bar', ['uses' => 'BarController@create', 'as' => 'bar_create'])
    );

    $routesByMethod = toCompiledRouteCollection($this->routeCollection)->getRoutesByMethod();

    expect(array_keys($routesByMethod['GET']))->toBe(['foo/index', 'foo/show']);
    expect(array_keys($routesByMethod['HEAD']))->toBe(['foo/index', 'foo/show']);
    expect(array_keys($routesByMethod['POST']))->toBe(['bar']);
    expect($routesByMethod['GET']['foo/index']->getName())->toBe('foo_index');
    expect($routesByMethod['POST']['bar']->getName())->toBe('bar_create');
});

test('compiled route collection dynamically added route overrides cached route for same uri', function () {
    $this->routeCollection->add(
        new Route('GET', 'users', ['uses' => 'UsersController@index', 'as' => 'users.cached'])
    );

    $compiled = toCompiledRouteCollection($this->routeCollection);

    $compiled->add(
        new Route('GET', 'users', ['uses' => 'UsersController@dynamicIndex', 'as' => 'users.dynamic'])
    );

    $routes = $compiled->get('GET');

    expect($routes)->toHaveCount(1);
    expect($routes['users']->getName())->toBe('users.dynamic');
});

test('compiled route collection request method not allowed', function () {
    $this->routeCollection->add(
        new Route('GET', 'users', ['uses' => 'UsersController@index', 'as' => 'users'])
    );

    $request = Request::create('users', 'POST');

    expect(fn () => toCompiledRouteCollection($this->routeCollection)->match($request))->toThrow(function (MethodNotAllowedHttpException $e) {
        expect($e->getMessage())->toBe('The POST method is not supported for route users. Supported methods: GET, HEAD.')
            ->and($e->getHeaders())->toBe(['Allow' => 'GET, HEAD']);
    });
});

test('compiled route collection throws not found for unmatched path', function () {
    $this->routeCollection->add(
        new Route('GET', 'users', ['uses' => 'UsersController@index', 'as' => 'users'])
    );

    $request = Request::create('this-path-does-not-exist', 'GET');

    toCompiledRouteCollection($this->routeCollection)->match($request);
})->throws(NotFoundHttpException::class, 'The route this-path-does-not-exist could not be found.');

function toCompiledRouteCollection(RouteCollection $routeCollection): CompiledRouteCollection
{
    return $routeCollection->toCompiledRouteCollection(new Router(new SignalDispatcher, new ControlPanel), new ControlPanel);
}
