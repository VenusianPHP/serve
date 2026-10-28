<?php

namespace Venusian\Serve\Tests\Routing;

use BadMethodCallException;
use FooController;
use Voyager\Vessel\ControlPanel;
use Voyager\Signals\SignalDispatcher;
use Mariner\Http\Request;
use Mariner\Routing\CallableDispatcher;
use Mariner\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Mariner\Routing\Router;
use Mariner\Routing\RouteRegistrar;
use Venusian\Serve\Tests\Routing\Fixtures\CategoryBackedEnum;
use Venusian\Serve\Tests\Routing\Fixtures\IntegerEnum;
use Venusian\Serve\Tests\Routing\Fixtures\RouteDomainEnum;
use Venusian\Serve\Tests\Routing\Fixtures\RouteNameEnum;
use InvalidArgumentException;
use Stringable;

require_once __DIR__.'/Fixtures/Enums.php';

beforeEach(function () {
    $container = new ControlPanel;
    $container->bind(CallableDispatcherContract::class, fn ($app) => new CallableDispatcher($app));

    $this->router = new Router(new SignalDispatcher, $container);
});

test('middleware fluent registration', function () {
    $this->router->middleware(['one', 'two'])->get('users', function () {
        return 'all-users';
    });

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));
    expect(getRoute($this->router)->middleware())->toEqual(['one', 'two']);

    $this->router->middleware('three', 'four')->get('users', function () {
        return 'all-users';
    });

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));
    expect(getRoute($this->router)->middleware())->toEqual(['three', 'four']);

    $this->router->get('users', function () {
        return 'all-users';
    })->middleware('five', 'six');

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));
    expect(getRoute($this->router)->middleware())->toEqual(['five', 'six']);

    $this->router->middleware('seven')->get('users', function () {
        return 'all-users';
    });

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));
    expect(getRoute($this->router)->middleware())->toEqual(['seven']);
});

test('null namespace is respected', function () {
    $this->router->middleware(['one'])->namespace(null)->get('users', function () {
        return 'all-users';
    });

    expect(getRoute($this->router)->getAction()['namespace'])->toBeNull();
});

test('middleware as stringable object', function () {
    $one = new class implements Stringable
    {
        public function __toString()
        {
            return 'one';
        }
    };

    $this->router->middleware($one)->get('users', function () {
        return 'all-users';
    });

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));
    expect(getRoute($this->router)->middleware())->toBe(['one']);
});

test('middleware as stringable object on route instance', function () {
    $one = new class implements Stringable
    {
        public function __toString()
        {
            return 'one';
        }
    };

    $this->router->get('users', function () {
        return 'all-users';
    })->middleware($one);

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));
    expect(getRoute($this->router)->middleware())->toBe(['one']);
});

test('middleware as array with stringables', function () {
    $one = new class implements Stringable
    {
        public function __toString()
        {
            return 'one';
        }
    };

    $this->router->middleware([$one, 'two'])->get('users', function () {
        return 'all-users';
    });

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));
    expect(getRoute($this->router)->middleware())->toBe(['one', 'two']);
});

test('middleware as null', function () {
    $this->router->middleware(null)->get('users', function () {
        return 'all-users';
    });

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));
    expect(getRoute($this->router)->middleware())->toBe([]);

    $this->router->get('users', function () {
        return 'all-users';
    })->middleware(null);

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));
    expect(getRoute($this->router)->middleware())->toBe([]);
});

test('without middleware registration', function () {
    $this->router->middleware(['one', 'two'])->get('users', function () {
        return 'all-users';
    })->withoutMiddleware('one');

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));

    expect(getRoute($this->router)->excludedMiddleware())->toEqual(['one']);
});

test('get route with trashed', function () {
    $route = $this->router->get('users', [RouteRegistrarControllerStub::class, 'index'])->withTrashed();

    expect($route->allowsTrashedBindings())->toBeTrue();
});

test('resource with trashed', function () {
    $this->router->resource('users', RouteRegistrarControllerStub::class)
        ->only(['index', 'destroy'])
        ->withTrashed([
            'index',
            'destroy',
        ]);

    /** @var \Mariner\Routing\Route $route */
    foreach ($this->router->getRoutes() as $route) {
        expect($route->allowsTrashedBindings())->toBeTrue();
    }
});

test('fallback route', function () {
    $route = $this->router->fallback(function () {
        return 'milwad';
    });

    expect($route->isFallback)->toBeTrue();
});

test('set fallback route', function () {
    $route = $this->router->fallback(function () {
        return 'milwad';
    });
    $route->setFallback(false);

    expect($route->isFallback)->toBeFalse();

    $route->setFallback(true);

    expect($route->isFallback)->toBeTrue();
});

test('can register get route with closure action', function () {
    $this->router->middleware('get-middleware')->get('users', function () {
        return 'all-users';
    });

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));
    seeMiddleware($this->router, 'get-middleware');
});

test('can register post route with closure action', function () {
    $this->router->middleware('post-middleware')->post('users', function () {
        return 'saved';
    });

    seeResponse($this->router, 'saved', Request::create('users', 'POST'));
    seeMiddleware($this->router, 'post-middleware');
});

test('can register query route with controller action', function () {
    $this->router->middleware('query-middleware')
        ->query('users', RouteRegistrarControllerStub::class.'@index');

    seeResponse($this->router, 'controller', Request::create('users', 'QUERY'));
    seeMiddleware($this->router, 'query-middleware');
});

test('can register any route with closure action', function () {
    $this->router->middleware('test-middleware')->any('users', function () {
        return 'anything';
    });

    seeResponse($this->router, 'anything', Request::create('users', 'PUT'));
    seeMiddleware($this->router, 'test-middleware');
});

test('can register match route with closure action', function () {
    $this->router->middleware('match-middleware')->match(['DELETE'], 'users', function () {
        return 'deleted';
    });

    seeResponse($this->router, 'deleted', Request::create('users', 'DELETE'));
    seeMiddleware($this->router, 'match-middleware');
});

test('can register route with array and closure action', function () {
    $this->router->middleware('patch-middleware')->patch('users', [function () {
        return 'updated';
    }]);

    seeResponse($this->router, 'updated', Request::create('users', 'PATCH'));
    seeMiddleware($this->router, 'patch-middleware');
});

test('can register route with array and closure uses action', function () {
    $this->router->middleware('put-middleware')->put('users', ['uses' => function () {
        return 'replaced';
    }]);

    seeResponse($this->router, 'replaced', Request::create('users', 'PUT'));
    seeMiddleware($this->router, 'put-middleware');
});

test('can register route with controller action', function () {
    $this->router->middleware('controller-middleware')
        ->get('users', RouteRegistrarControllerStub::class.'@index');

    seeResponse($this->router, 'controller', Request::create('users', 'GET'));
    seeMiddleware($this->router, 'controller-middleware');
});

test('can register route with controller action array', function () {
    $this->router->middleware('controller-middleware')
        ->get('users', [RouteRegistrarControllerStub::class, 'index']);

    seeResponse($this->router, 'controller', Request::create('users', 'GET'));
    seeMiddleware($this->router, 'controller-middleware');
});

test('can register namespaced group route with controller action array', function () {
    $this->router->group(['namespace' => 'WhatEver'], function () {
        $this->router->middleware('controller-middleware')
            ->get('users', [RouteRegistrarControllerStub::class, 'index']);
    });

    seeResponse($this->router, 'controller', Request::create('users', 'GET'));
    seeMiddleware($this->router, 'controller-middleware');

    $this->router->group(['namespace' => 'WhatEver'], function () {
        $this->router->middleware('controller-middleware')
            ->get('users', ['\\'.RouteRegistrarControllerStub::class, 'index']);
    });

    seeResponse($this->router, 'controller', Request::create('users', 'GET'));
    seeMiddleware($this->router, 'controller-middleware');
});

test('can register route with array and controller action', function () {
    $this->router->middleware('controller-middleware')->put('users', [
        'uses' => RouteRegistrarControllerStub::class.'@index',
    ]);

    seeResponse($this->router, 'controller', Request::create('users', 'PUT'));
    seeMiddleware($this->router, 'controller-middleware');
});

test('can register group with middleware', function () {
    $this->router->middleware('group-middleware')->group(function ($router) {
        $router->get('users', function () {
            return 'all-users';
        });
    });

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));
    seeMiddleware($this->router, 'group-middleware');
});

test('can register group without middleware', function () {
    $this->router->withoutMiddleware('one')->group(function ($router) {
        $router->get('users', function () {
            return 'all-users';
        })->middleware(['one', 'two']);
    });

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));
    expect(getRoute($this->router)->excludedMiddleware())->toEqual(['one']);
});

test('can register group with stringable middleware', function () {
    $one = new class implements Stringable
    {
        public function __toString()
        {
            return 'one';
        }
    };

    $this->router->middleware($one)->group(function ($router) {
        $router->get('users', function () {
            return 'all-users';
        });
    });

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));
    seeMiddleware($this->router, 'one');
});

test('can register group with namespace', function () {
    $this->router->namespace('App\Http\Controllers')->group(function ($router) {
        $router->get('users', 'UsersController@index');
    });

    expect(getRoute($this->router)->getAction()['uses'])->toBe('App\Http\Controllers\UsersController@index');

    $this->router->namespace('App\Http\Controllers')->group(function ($router) {
        $router->redirect('users', '/');
    });

    expect(getRoute($this->router)->getAction()['uses'])->toBe('\Mariner\Routing\RedirectController@__invoke');
});

test('can register group with prefix', function () {
    $this->router->prefix('api')->group(function ($router) {
        $router->get('users', 'UsersController@index');
    });

    expect(getRoute($this->router)->uri())->toBe('api/users');
});

test('can register group with prefix and where', function () {
    $this->router->prefix('foo/{bar}')->where(['bar' => '[0-9]+'])->group(function ($router) {
        $router->get('here', function () {
            return 'good';
        });
    });

    seeResponse($this->router, 'good', Request::create('foo/12345/here', 'GET'));
});

test('can register group with name prefix', function () {
    $this->router->name('api.')->group(function ($router) {
        $router->get('users', 'UsersController@index')->name('users');
    });

    expect(getRoute($this->router)->getName())->toBe('api.users');
});

test('can register group with domain', function () {
    $this->router->domain('{account}.myapp.com')->group(function ($router) {
        $router->get('users', 'UsersController@index');
    });

    expect(getRoute($this->router)->getDomain())->toBe('{account}.myapp.com');
});

test('can register group with domain and name prefix', function () {
    $this->router->domain('{account}.myapp.com')->name('api.')->group(function ($router) {
        $router->get('users', 'UsersController@index')->name('users');
    });

    expect(getRoute($this->router)->getDomain())->toBe('{account}.myapp.com');
    expect(getRoute($this->router)->getName())->toBe('api.users');
});

test('can register group with controller', function () {
    $this->router->controller(RouteRegistrarControllerStub::class)->group(function ($router) {
        $router->get('users', 'index');
    });

    expect(getRoute($this->router)->getAction()['uses'])->toBe(RouteRegistrarControllerStub::class.'@index');
});

test('can override group controller with string syntax', function () {
    $this->router->controller(RouteRegistrarControllerStub::class)->group(function ($router) {
        $router->get('users', 'UserController@index');
    });

    expect(getRoute($this->router)->getAction()['uses'])->toBe('UserController@index');
});

test('can override group controller with closure syntax', function () {
    $this->router->controller(RouteRegistrarControllerStub::class)->group(function ($router) {
        $router->get('users', function () {
            return 'hello world';
        });
    });

    seeResponse($this->router, 'hello world', Request::create('users', 'GET'));
});

test('can override group controller with invokable controller syntax', function () {
    $this->router->controller(RouteRegistrarControllerStub::class)->group(function ($router) {
        $router->get('users', InvokableRouteRegistrarControllerStub::class);
    });

    expect(getRoute($this->router)->getAction()['uses'])->toBe(InvokableRouteRegistrarControllerStub::class.'@__invoke');
});

test('will use the latest group controller', function () {
    $this->router->controller(RouteRegistrarControllerStub::class)->group(function ($router) {
        $router->group(['controller' => FooController::class], function ($router) {
            $router->get('users', 'index');
        });
    });

    expect(getRoute($this->router)->getAction()['uses'])->toBe(FooController::class.'@index');
});

test('can override group controller with array syntax', function () {
    $this->router->controller(RouteRegistrarControllerStub::class)->group(function ($router) {
        $router->get('users', [FooController::class, 'index']);
    });

    expect(getRoute($this->router)->getAction()['uses'])->toBe(FooController::class.'@index');
});

test('route grouping without prefix', function () {
    $this->router->group([], function ($router) {
        $router->prefix('bar')->get('baz', ['as' => 'baz', function () {
            return 'hello';
        }]);
    });
    seeResponse($this->router, 'hello', Request::create('bar/baz', 'GET'));
});

test('route group chaining', function () {
    $this->router
        ->group([], function ($router) {
            $router->get('foo', function () {
                return 'hello';
            });
        })
        ->group([], function ($router) {
            $router->get('bar', function () {
                return 'goodbye';
            });
        });

    $routeCollection = $this->router->getRoutes();

    expect($routeCollection->match(Request::create('foo', 'GET')))->toBeInstanceOf(\Mariner\Routing\Route::class);
    expect($routeCollection->match(Request::create('bar', 'GET')))->toBeInstanceOf(\Mariner\Routing\Route::class);
});

test('registering non approved attributes throws', function () {

    $this->router->domain('foo')->unsupportedMethod('bar')->group(function ($router) {
        //
    });
})->throws(BadMethodCallException::class, 'Method Mariner\Routing\RouteRegistrar::unsupportedMethod does not exist.');

test('can set without scoped bindings', function () {
    $route = $this->router->withoutScopedBindings()->get('users', function () {
        return 'all-users';
    });

    expect($route->preventsScopedBindings())->toBeTrue();
});

test('can set without scoped bindings on group', function () {
    $this->router->withoutScopedBindings()->group(function ($router) {
        $router->get('foo', function () {
            return 'hello';
        });
    });

    $route = $this->router->getRoutes()->getRoutes()[0];

    expect($route->preventsScopedBindings())->toBeTrue();
});

test('can set scope bindings', function () {
    $route = $this->router->scopeBindings()->get('users', function () {
        return 'all-users';
    });

    expect($route->enforcesScopedBindings())->toBeTrue();
});

test('can set scope bindings on group', function () {
    $this->router->scopeBindings()->group(function ($router) {
        $router->get('foo', function () {
            return 'hello';
        });
    });

    $route = $this->router->getRoutes()->getRoutes()[0];

    expect($route->enforcesScopedBindings())->toBeTrue();
});

test('can set route metadata', function () {
    $route = $this->router
        ->metadata(['head' => ['title' => 'Users']])
        ->get('users', function () {
            return 'all-users';
        })
        ->metadata(['head' => ['description' => 'All users.']]);

    expect($route->getMetadata('head'))->toBe([
        'title' => 'Users',
        'description' => 'All users.',
    ]);
    expect($route->getMetadata('head.title'))->toBe('Users');
});

test('can set route metadata on group', function () {
    $this->router
        ->metadata(['head' => ['robots' => ['noindex', 'nofollow']]])
        ->group(function ($router) {
            $router
                ->metadata(['head' => ['title' => 'Users']])
                ->get('users', function () {
                    return 'all-users';
                });
        });

    $route = $this->router->getRoutes()->getRoutes()[0];

    expect($route->getMetadata('head'))->toBe([
        'robots' => ['noindex', 'nofollow'],
        'title' => 'Users',
    ]);
});

test('route metadata list values replace parent values', function () {
    $this->router
        ->metadata(['head' => ['robots' => ['index', 'follow']]])
        ->group(function ($router) {
            $router
                ->metadata(['head' => ['robots' => ['noindex']]])
                ->get('users', function () {
                    return 'all-users';
                });
        });

    $route = $this->router->getRoutes()->getRoutes()[0];

    expect($route->getMetadata('head.robots'))->toBe(['noindex']);
});

test('can set route metadata on group using array syntax', function () {
    $this->router->group(['metadata' => ['head' => ['title' => 'Users']]], function ($router) {
        $router->get('users', function () {
            return 'all-users';
        });
    });

    $route = $this->router->getRoutes()->getRoutes()[0];

    expect($route->getMetadata('head'))->toBe(['title' => 'Users']);
});

test('empty route metadata array replaces parent value', function () {
    $this->router
        ->metadata(['head' => ['title' => 'Users']])
        ->group(function ($router) {
            $router
                ->metadata(['head' => []])
                ->get('users', function () {
                    return 'all-users';
                });
        });

    $route = $this->router->getRoutes()->getRoutes()[0];

    expect($route->getMetadata('head'))->toBe([]);
});

test('route metadata attribute requires array', function () {

    (new RouteRegistrar($this->router))->attribute('metadata', 'invalid');
})->throws(InvalidArgumentException::class, 'Attribute [metadata] expects an array.');

test('route metadata does not collide with route actions', function () {
    $route = $this->router
        ->middleware('web')
        ->metadata(['middleware' => 'metadata'])
        ->get('users', function () {
            return 'all-users';
        });

    expect($route->getMetadata('middleware'))->toBe('metadata');
    expect($route->getAction('middleware'))->toBe(['web']);
});

test('route metadata merges through deeply nested groups', function () {
    $this->router
        ->metadata(['head' => ['title' => 'Outer', 'description' => 'Outer description']])
        ->group(function ($router) {
            $router
                ->metadata(['head' => ['title' => 'Middle', 'author' => 'Taylor']])
                ->group(function ($router) {
                    $router
                        ->metadata(['head' => ['title' => 'Inner']])
                        ->get('users', function () {
                            return 'all-users';
                        });
                });
        });

    $route = $this->router->getRoutes()->getRoutes()[0];

    expect($route->getMetadata('head'))->toBe([
        'title' => 'Inner',
        'description' => 'Outer description',
        'author' => 'Taylor',
    ]);
});

test('set metadata replaces existing metadata', function () {
    $route = $this->router
        ->metadata(['head' => ['title' => 'Original', 'description' => 'Goes away']])
        ->get('users', function () {
            return 'all-users';
        });

    $route->setMetadata(['head' => ['title' => 'Replaced']]);

    expect($route->getMetadata())->toBe(['head' => ['title' => 'Replaced']]);
});

test('can register resource', function () {
    $this->router->middleware('resource-middleware')
        ->resource('users', RouteRegistrarControllerStub::class);

    seeResponse($this->router, 'deleted', Request::create('users/1', 'DELETE'));
    seeMiddleware($this->router, 'resource-middleware');
});

test('can set route metadata on resource', function () {
    $this->router->resource('users', RouteRegistrarControllerStub::class)
        ->metadata(['head' => ['title' => 'Users']]);

    expect($this->router->getRoutes()->getByName('users.index')->getMetadata('head'))->toBe(['title' => 'Users']);
});

test('can set route metadata on resource group', function () {
    $this->router
        ->metadata(['head' => ['title' => 'Users']])
        ->resource('users', RouteRegistrarControllerStub::class);

    expect($this->router->getRoutes()->getByName('users.index')->getMetadata('head'))->toBe(['title' => 'Users']);
});

test('can set route metadata on api resource', function () {
    $this->router->apiResource('users', RouteRegistrarControllerStub::class)
        ->metadata(['head' => ['title' => 'Users']]);

    expect($this->router->getRoutes()->getByName('users.index')->getMetadata('head'))->toBe(['title' => 'Users']);
});

test('can register resources with except option', function () {
    $this->router->resources([
        'resource-one' => RouteRegistrarControllerStubOne::class,
        'resource-two' => RouteRegistrarControllerStubTwo::class,
        'resource-three' => RouteRegistrarControllerStubThree::class,
    ], ['except' => ['create', 'show']]);

    expect($this->router->getRoutes())->toHaveCount(15);

    foreach (['one', 'two', 'three'] as $resource) {
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.index'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.store'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.edit'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.update'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.destroy'))->toBeTrue();

        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.create'))->toBeFalse();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.show'))->toBeFalse();
    }
});

test('can register resources with only option', function () {
    $this->router->resources([
        'resource-one' => RouteRegistrarControllerStubOne::class,
        'resource-two' => RouteRegistrarControllerStubTwo::class,
        'resource-three' => RouteRegistrarControllerStubThree::class,
    ], ['only' => ['create', 'show']]);

    expect($this->router->getRoutes())->toHaveCount(6);

    foreach (['one', 'two', 'three'] as $resource) {
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.create'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.show'))->toBeTrue();

        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.index'))->toBeFalse();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.store'))->toBeFalse();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.edit'))->toBeFalse();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.update'))->toBeFalse();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.destroy'))->toBeFalse();
    }
});

test('can register resources without option', function () {
    $this->router->resources([
        'resource-one' => RouteRegistrarControllerStubOne::class,
        'resource-two' => RouteRegistrarControllerStubTwo::class,
        'resource-three' => RouteRegistrarControllerStubThree::class,
    ]);

    expect($this->router->getRoutes())->toHaveCount(21);

    foreach (['one', 'two', 'three'] as $resource) {
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.index'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.create'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.store'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.show'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.edit'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.update'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.destroy'))->toBeTrue();
    }
});

test('can register resource with missing option', function () {
    $this->router->middleware('resource-middleware')
        ->resource('users', RouteRegistrarControllerStub::class)
        ->missing(function () {
            return 'missing';
        });

    expect($this->router->getRoutes()->getByName('users.show')->getMissing())->toBeCallable();
    expect($this->router->getRoutes()->getByName('users.edit')->getMissing())->toBeCallable();
    expect($this->router->getRoutes()->getByName('users.update')->getMissing())->toBeCallable();
    expect($this->router->getRoutes()->getByName('users.destroy')->getMissing())->toBeCallable();

    expect($this->router->getRoutes()->getByName('users.index')->getMissing())->toBeNull();
    expect($this->router->getRoutes()->getByName('users.create')->getMissing())->toBeNull();
    expect($this->router->getRoutes()->getByName('users.store')->getMissing())->toBeNull();
});

test('can access registered resource routes as route collection', function () {
    $resource = $this->router->middleware('resource-middleware')
        ->resource('users', RouteRegistrarControllerStub::class)
        ->register();

    expect($resource->getRoutes())->toHaveCount(7);

    expect($this->router->getRoutes()->hasNamedRoute('users.index'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('users.create'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('users.store'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('users.show'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('users.edit'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('users.update'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('users.destroy'))->toBeTrue();
});

test('can limit methods on registered resource', function () {
    $this->router->resource('users', RouteRegistrarControllerStub::class)
        ->only('index', 'show', 'destroy');

    expect($this->router->getRoutes())->toHaveCount(3);

    expect($this->router->getRoutes()->hasNamedRoute('users.index'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('users.show'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('users.destroy'))->toBeTrue();
});

test('can exclude methods on registered resource', function () {
    $this->router->resource('users', RouteRegistrarControllerStub::class)
        ->except(['index', 'create', 'store', 'show', 'edit']);

    expect($this->router->getRoutes())->toHaveCount(2);

    expect($this->router->getRoutes()->hasNamedRoute('users.update'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('users.destroy'))->toBeTrue();
});

test('can limit and exclude methods on registered resource', function () {
    $this->router->resource('users', RouteRegistrarControllerStub::class)
        ->only('index', 'show', 'destroy')
        ->except('destroy');

    expect($this->router->getRoutes())->toHaveCount(2);

    expect($this->router->getRoutes()->hasNamedRoute('users.index'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('users.show'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('users.destroy'))->toBeFalse();
});

test('can set shallow option on registered resource', function () {
    $this->router->resource('users.tasks', RouteRegistrarControllerStub::class)->shallow();

    expect($this->router->getRoutes())->toHaveCount(7);

    expect($this->router->getRoutes()->hasNamedRoute('users.tasks.index'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('tasks.show'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('users.tasks.show'))->toBeFalse();
});

test('can set scoped option on registered resource', function () {
    $this->router->resource('users.tasks', RouteRegistrarControllerStub::class)->scoped();
    $this->router->getRoutes()->refreshNameLookups();

    expect($this->router->getRoutes()->getByName('users.tasks.index')->bindingFields())->toBe(['user' => null]);
    expect($this->router->getRoutes()->getByName('users.tasks.show')->bindingFields())->toBe(['user' => null, 'task' => null]);

    $this->router->resource('users.tasks', RouteRegistrarControllerStub::class)->scoped([
        'task' => 'slug',
    ]);
    $this->router->getRoutes()->refreshNameLookups();
    expect($this->router->getRoutes()->getByName('users.tasks.index')->bindingFields())->toBe(['user' => null]);
    expect($this->router->getRoutes()->getByName('users.tasks.show')->bindingFields())->toBe(['user' => null, 'task' => 'slug']);
});

test('can exclude methods on registered api resource', function () {
    $this->router->apiResource('users', RouteRegistrarControllerStub::class)
        ->except(['index', 'show', 'store']);

    expect($this->router->getRoutes())->toHaveCount(2);

    expect($this->router->getRoutes()->hasNamedRoute('users.update'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('users.destroy'))->toBeTrue();
});

test('can register api resources with except option', function () {
    $this->router->apiResources([
        'resource-one' => RouteRegistrarControllerStubOne::class,
        'resource-two' => RouteRegistrarControllerStubTwo::class,
        'resource-three' => RouteRegistrarControllerStubThree::class,
    ], ['except' => ['create', 'show']]);

    expect($this->router->getRoutes())->toHaveCount(12);

    foreach (['one', 'two', 'three'] as $resource) {
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.index'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.store'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.update'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.destroy'))->toBeTrue();

        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.create'))->toBeFalse();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.show'))->toBeFalse();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.edit'))->toBeFalse();
    }
});

test('can register api resources with only option', function () {
    $this->router->apiResources([
        'resource-one' => RouteRegistrarControllerStubOne::class,
        'resource-two' => RouteRegistrarControllerStubTwo::class,
        'resource-three' => RouteRegistrarControllerStubThree::class,
    ], ['only' => ['index', 'show']]);

    expect($this->router->getRoutes())->toHaveCount(6);

    foreach (['one', 'two', 'three'] as $resource) {
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.index'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.show'))->toBeTrue();

        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.store'))->toBeFalse();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.update'))->toBeFalse();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.destroy'))->toBeFalse();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.create'))->toBeFalse();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.edit'))->toBeFalse();
    }
});

test('can register api resources without option', function () {
    $this->router->apiResources([
        'resource-one' => RouteRegistrarControllerStubOne::class,
        'resource-two' => RouteRegistrarControllerStubTwo::class,
        'resource-three' => RouteRegistrarControllerStubThree::class,
    ]);

    expect($this->router->getRoutes())->toHaveCount(15);

    foreach (['one', 'two', 'three'] as $resource) {
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.index'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.show'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.store'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.update'))->toBeTrue();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.destroy'))->toBeTrue();

        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.create'))->toBeFalse();
        expect($this->router->getRoutes()->hasNamedRoute('resource-'.$resource.'.edit'))->toBeFalse();
    }
});

test('user can register api resource', function () {
    $this->router->apiResource('users', RouteRegistrarControllerStub::class);

    expect($this->router->getRoutes())->toHaveCount(5);

    expect($this->router->getRoutes()->hasNamedRoute('users.create'))->toBeFalse();
    expect($this->router->getRoutes()->hasNamedRoute('users.edit'))->toBeFalse();
});

test('user can register api resource with except option', function () {
    $this->router->apiResource('users', RouteRegistrarControllerStub::class, [
        'except' => ['destroy'],
    ]);

    expect($this->router->getRoutes())->toHaveCount(4);

    expect($this->router->getRoutes()->hasNamedRoute('users.create'))->toBeFalse();
    expect($this->router->getRoutes()->hasNamedRoute('users.edit'))->toBeFalse();
    expect($this->router->getRoutes()->hasNamedRoute('users.destroy'))->toBeFalse();
});

test('user can register api resource with only option', function () {
    $this->router->apiResource('users', RouteRegistrarControllerStub::class, [
        'only' => ['index', 'show'],
    ]);

    expect($this->router->getRoutes())->toHaveCount(2);

    expect($this->router->getRoutes()->hasNamedRoute('users.index'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('users.show'))->toBeTrue();
});

test('can name routes on registered resource', function () {
    $this->router->resource('comments', RouteRegistrarControllerStub::class)
        ->only('create', 'store')->names('reply');

    $this->router->resource('users', RouteRegistrarControllerStub::class)
        ->only('create', 'store')->names([
            'create' => 'user.build',
            'store' => 'user.save',
        ]);

    $this->router->resource('posts', RouteRegistrarControllerStub::class)
        ->only('create', 'destroy')
        ->name('create', 'posts.make')
        ->name('destroy', 'posts.remove');

    expect($this->router->getRoutes()->hasNamedRoute('reply.create'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('reply.store'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.build'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.save'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('posts.make'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('posts.remove'))->toBeTrue();
});

test('can override parameters on registered resource', function () {
    $this->router->resource('users', RouteRegistrarControllerStub::class)
        ->parameters(['users' => 'admin_user']);

    $this->router->resource('posts', RouteRegistrarControllerStub::class)
        ->parameter('posts', 'topic');

    expect($this->router->getRoutes()->getByName('users.show')->uri)->toContain('admin_user');
    expect($this->router->getRoutes()->getByName('posts.show')->uri)->toContain('topic');
});

test('can set middleware on registered resource', function () {
    $this->router->resource('users', RouteRegistrarControllerStub::class)
        ->middleware(RouteRegistrarMiddlewareStub::class);

    seeMiddleware($this->router, RouteRegistrarMiddlewareStub::class);
});

test('can set middleware can on groups', function () {
    $this->router->can('test')->group(function ($router) {
        $router->get('/');
    });

    seeMiddleware($this->router, 'can:test');
});

test('can set middleware can with models on groups', function () {
    $this->router->can('view', 'post')->group(function ($router) {
        $router->get('/post/{post}');
    });

    seeMiddleware($this->router, 'can:view,post');
});

test('can set middleware can nested on groups', function () {
    $this->router->can('access-admin')->group(function ($router) {
        $router->can('edit', 'post')->group(function ($router) {
            $router->get('/post/{post}/edit');
        });
    });

    expect(getRoute($this->router)->middleware())->toEqual([
        'can:access-admin',
        'can:edit,post',
    ]);
});

test('can set middleware for specified methods on registered resource', function () {
    $this->router->resource('users', RouteRegistrarControllerStub::class)
        ->middleware('default')
        ->middlewareFor('index', RouteRegistrarMiddlewareStub::class)
        ->middlewareFor(['create', 'store'], 'one')
        ->middlewareFor(['edit'], ['one', 'two']);
    $this->router->getRoutes()->refreshNameLookups();

    expect($this->router->getRoutes()->getByName('users.index')->gatherMiddleware())->toEqual(['default', RouteRegistrarMiddlewareStub::class]);
    expect($this->router->getRoutes()->getByName('users.create')->gatherMiddleware())->toEqual(['default', 'one']);
    expect($this->router->getRoutes()->getByName('users.store')->gatherMiddleware())->toEqual(['default', 'one']);
    expect($this->router->getRoutes()->getByName('users.show')->gatherMiddleware())->toEqual(['default']);
    expect($this->router->getRoutes()->getByName('users.edit')->gatherMiddleware())->toEqual(['default', 'one', 'two']);
    expect($this->router->getRoutes()->getByName('users.update')->gatherMiddleware())->toEqual(['default']);
    expect($this->router->getRoutes()->getByName('users.destroy')->gatherMiddleware())->toEqual(['default']);

    $this->router->resource('users', RouteRegistrarControllerStub::class)
        ->middlewareFor('index', RouteRegistrarMiddlewareStub::class)
        ->middlewareFor(['create', 'store'], 'one')
        ->middlewareFor(['edit'], ['one', 'two'])
        ->middleware('default');
    $this->router->getRoutes()->refreshNameLookups();

    expect($this->router->getRoutes()->getByName('users.index')->gatherMiddleware())->toEqual([RouteRegistrarMiddlewareStub::class, 'default']);
    expect($this->router->getRoutes()->getByName('users.create')->gatherMiddleware())->toEqual(['one', 'default']);
    expect($this->router->getRoutes()->getByName('users.store')->gatherMiddleware())->toEqual(['one', 'default']);
    expect($this->router->getRoutes()->getByName('users.show')->gatherMiddleware())->toEqual(['default']);
    expect($this->router->getRoutes()->getByName('users.edit')->gatherMiddleware())->toEqual(['one', 'two', 'default']);
    expect($this->router->getRoutes()->getByName('users.update')->gatherMiddleware())->toEqual(['default']);
    expect($this->router->getRoutes()->getByName('users.destroy')->gatherMiddleware())->toEqual(['default']);
});

test('resource without middleware registration', function () {
    $this->router->resource('users', RouteRegistrarControllerStub::class)
        ->only('index')
        ->middleware(['one', 'two'])
        ->withoutMiddleware('one');

    seeResponse($this->router, 'controller', Request::create('users', 'GET'));

    expect(getRoute($this->router)->excludedMiddleware())->toEqual(['one']);
});

test('can set excluded middleware for specified methods on registered resource', function () {
    $this->router->resource('users', RouteRegistrarControllerStub::class)
        ->withoutMiddleware('one')
        ->withoutMiddlewareFor('index', 'two')
        ->withoutMiddlewareFor(['create', 'store'], 'three')
        ->withoutMiddlewareFor(['edit'], ['four', 'five']);

    expect($this->router->getRoutes()->getByName('users.index')->excludedMiddleware())->toEqual(['one', 'two']);
    expect($this->router->getRoutes()->getByName('users.create')->excludedMiddleware())->toEqual(['one', 'three']);
    expect($this->router->getRoutes()->getByName('users.store')->excludedMiddleware())->toEqual(['one', 'three']);
    expect($this->router->getRoutes()->getByName('users.show')->excludedMiddleware())->toEqual(['one']);
    expect($this->router->getRoutes()->getByName('users.edit')->excludedMiddleware())->toEqual(['one', 'four', 'five']);
    expect($this->router->getRoutes()->getByName('users.update')->excludedMiddleware())->toEqual(['one']);
    expect($this->router->getRoutes()->getByName('users.destroy')->excludedMiddleware())->toEqual(['one']);
});

test('resource with middleware as stringable', function () {
    $one = new class implements Stringable
    {
        public function __toString()
        {
            return 'one';
        }
    };

    $this->router->resource('users', RouteRegistrarControllerStub::class)
        ->only('index')
        ->middleware([$one, 'two'])
        ->withoutMiddleware('one');

    seeResponse($this->router, 'controller', Request::create('users', 'GET'));

    expect(getRoute($this->router)->middleware())->toEqual(['one', 'two']);
    expect(getRoute($this->router)->excludedMiddleware())->toEqual(['one']);
});

test('resource wheres', function () {
    $wheres = [
        'user' => '\d+',
        'test' => '[a-z]+',
    ];

    $this->router->resource('users', RouteRegistrarControllerStub::class)
        ->where($wheres);

    /** @var \Mariner\Routing\Route $route */
    foreach ($this->router->getRoutes() as $route) {
        expect($route->wheres)->toEqual($wheres);
    }
});

test('where number registration', function () {
    $wheres = ['foo' => '[0-9]+', 'bar' => '[0-9]+'];

    $this->router->get('/{foo}/{bar}')->whereNumber(['foo', 'bar']);
    $this->router->get('/api/{bar}/{foo}')->whereNumber(['bar', 'foo']);

    /** @var \Mariner\Routing\Route $route */
    foreach ($this->router->getRoutes() as $route) {
        expect($route->wheres)->toEqual($wheres);
    }
});

test('where alpha registration', function () {
    $wheres = ['foo' => '[a-zA-Z]+', 'bar' => '[a-zA-Z]+'];

    $this->router->get('/{foo}/{bar}')->whereAlpha(['foo', 'bar']);
    $this->router->get('/api/{bar}/{foo}')->whereAlpha(['bar', 'foo']);

    /** @var \Mariner\Routing\Route $route */
    foreach ($this->router->getRoutes() as $route) {
        expect($route->wheres)->toEqual($wheres);
    }
});

test('where alpha numeric registration', function () {
    $wheres = ['1a2b3c' => '[a-zA-Z0-9]+'];

    $this->router->get('/{foo}')->whereAlphaNumeric(['1a2b3c']);

    /** @var \Mariner\Routing\Route $route */
    foreach ($this->router->getRoutes() as $route) {
        expect($route->wheres)->toEqual($wheres);
    }
});

test('where ulid registration', function () {
    $this->router->get('/{foo}')->whereUlid('foo');

    expect(getRoute($this->router)->matches(Request::create('/01ARZ3NDEKTSV4RRFFQ69G5FAV', 'GET')))->toBeTrue();
    expect(getRoute($this->router)->matches(Request::create('/01ARZ3NDEKTSV4RRFFQ69G5FA', 'GET')))->toBeFalse();
    expect(getRoute($this->router)->matches(Request::create('/01ARZ3NDEKTSV4RRFFQ69G5FAI', 'GET')))->toBeFalse();
    expect(getRoute($this->router)->matches(Request::create('/81ARZ3NDEKTSV4RRFFQ69G5FAV', 'GET')))->toBeFalse();
});

test('where uuid registration', function () {
    $this->router->get('/{foo}')->whereUuid('foo');

    expect(getRoute($this->router)->matches(Request::create('/2cd90b6d-3c34-4a0a-9d0d-9d0b7b1a2e6f', 'GET')))->toBeTrue();
    expect(getRoute($this->router)->matches(Request::create('/2CD90B6D-3C34-4A0A-9D0D-9D0B7B1A2E6F', 'GET')))->toBeTrue();
    expect(getRoute($this->router)->matches(Request::create('/2cd90b6d3c344a0a9d0d9d0b7b1a2e6f', 'GET')))->toBeFalse();
    expect(getRoute($this->router)->matches(Request::create('/2cd90b6d-3c34-4a0a-9d0d-9d0b7b1a2e6', 'GET')))->toBeFalse();
});

test('where in registration', function () {
    $wheres = ['foo' => 'one|two', 'bar' => 'one|two'];

    $this->router->get('/{foo}/{bar}')->whereIn(['foo', 'bar'], ['one', 'two']);
    $this->router->get('/api/{bar}/{foo}')->whereIn(['bar', 'foo'], ['one', 'two']);

    /** @var \Mariner\Routing\Route $route */
    foreach ($this->router->getRoutes() as $route) {
        expect($route->wheres)->toEqual($wheres);
    }
});

test('where in enum registration', function () {
    $this->router->get('/posts/{category}')->whereIn('category', CategoryBackedEnum::cases());

    $invalidRequest = Request::create('/posts/invalid-value', 'GET');
    expect(getRoute($this->router)->matches($invalidRequest))->toBeFalse();

    foreach (CategoryBackedEnum::cases() as $case) {
        $request = Request::create('/posts/'.$case->value, 'GET');
        expect(getRoute($this->router)->matches($request))->toBeTrue();
    }
});

test('group where number registration on route registrar', function () {
    $wheres = ['foo' => '[0-9]+', 'bar' => '[0-9]+'];

    $this->router->prefix('/{foo}/{bar}')->whereNumber(['foo', 'bar'])->group(function ($router) {
        $router->get('/');
    });

    $this->router->prefix('/api/{bar}/{foo}')->whereNumber(['bar', 'foo'])->group(function ($router) {
        $router->get('/');
    });

    /** @var \Mariner\Routing\Route $route */
    foreach ($this->router->getRoutes() as $route) {
        expect($route->wheres)->toEqual($wheres);
    }
});

test('group where alpha registration on route registrar', function () {
    $wheres = ['foo' => '[a-zA-Z]+', 'bar' => '[a-zA-Z]+'];

    $this->router->prefix('/{foo}/{bar}')->whereAlpha(['foo', 'bar'])->group(function ($router) {
        $router->get('/');
    });

    $this->router->prefix('/api/{bar}/{foo}')->whereAlpha(['bar', 'foo'])->group(function ($router) {
        $router->get('/');
    });

    /** @var \Mariner\Routing\Route $route */
    foreach ($this->router->getRoutes() as $route) {
        expect($route->wheres)->toEqual($wheres);
    }
});

test('group where alpha numeric registration on route registrar', function () {
    $wheres = ['1a2b3c' => '[a-zA-Z0-9]+'];

    $this->router->prefix('/{foo}')->whereAlphaNumeric(['1a2b3c'])->group(function ($router) {
        $router->get('/');
    });

    /** @var \Mariner\Routing\Route $route */
    foreach ($this->router->getRoutes() as $route) {
        expect($route->wheres)->toEqual($wheres);
    }
});

test('group where in registration on route registrar', function () {
    $wheres = ['foo' => 'one|two', 'bar' => 'one|two'];

    $this->router->prefix('/{foo}/{bar}')->whereIn(['foo', 'bar'], ['one', 'two'])->group(function ($router) {
        $router->get('/');
    });

    $this->router->prefix('/api/{bar}/{foo}')->whereIn(['bar', 'foo'], ['one', 'two'])->group(function ($router) {
        $router->get('/');
    });

    /** @var \Mariner\Routing\Route $route */
    foreach ($this->router->getRoutes() as $route) {
        expect($route->wheres)->toEqual($wheres);
    }
});

test('group where number registration on router', function () {
    $wheres = ['foo' => '[0-9]+', 'bar' => '[0-9]+'];

    $this->router->whereNumber(['foo', 'bar'])->prefix('/{foo}/{bar}')->group(function ($router) {
        $router->get('/');
    });

    $this->router->whereNumber(['bar', 'foo'])->prefix('/api/{bar}/{foo}')->group(function ($router) {
        $router->get('/');
    });

    /** @var \Mariner\Routing\Route $route */
    foreach ($this->router->getRoutes() as $route) {
        expect($route->wheres)->toEqual($wheres);
    }
});

test('group where alpha registration on router', function () {
    $wheres = ['foo' => '[a-zA-Z]+', 'bar' => '[a-zA-Z]+'];

    $this->router->whereAlpha(['foo', 'bar'])->prefix('/{foo}/{bar}')->group(function ($router) {
        $router->get('/');
    });

    $this->router->whereAlpha(['bar', 'foo'])->prefix('/api/{bar}/{foo}')->group(function ($router) {
        $router->get('/');
    });

    /** @var \Mariner\Routing\Route $route */
    foreach ($this->router->getRoutes() as $route) {
        expect($route->wheres)->toEqual($wheres);
    }
});

test('group where alpha numeric registration on router', function () {
    $wheres = ['1a2b3c' => '[a-zA-Z0-9]+'];

    $this->router->whereAlphaNumeric(['1a2b3c'])->prefix('/{foo}')->group(function ($router) {
        $router->get('/');
    });

    /** @var \Mariner\Routing\Route $route */
    foreach ($this->router->getRoutes() as $route) {
        expect($route->wheres)->toEqual($wheres);
    }
});

test('group where in registration on router', function () {
    $wheres = ['foo' => 'one|two', 'bar' => 'one|two'];

    $this->router->whereIn(['foo', 'bar'], ['one', 'two'])->prefix('/{foo}/{bar}')->group(function ($router) {
        $router->get('/');
    });

    $this->router->whereIn(['bar', 'foo'], ['one', 'two'])->prefix('/api/{bar}/{foo}')->group(function ($router) {
        $router->get('/');
    });

    /** @var \Mariner\Routing\Route $route */
    foreach ($this->router->getRoutes() as $route) {
        expect($route->wheres)->toEqual($wheres);
    }
});

test('can set route name', function () {
    $this->router->as('users.index')->get('users', function () {
        return 'all-users';
    });

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));
    expect(getRoute($this->router)->getName())->toBe('users.index');
});

test('can set route name using name alias', function () {
    $this->router->name('users.index')->get('users', function () {
        return 'all-users';
    });

    seeResponse($this->router, 'all-users', Request::create('users', 'GET'));
    expect(getRoute($this->router)->getName())->toBe('users.index');
});

test('can set route name with non callable controller action array', function () {
    $this->router->name('users.missing')->get('users', [RouteRegistrarControllerStub::class, 'missing']);

    expect(getRoute($this->router)->getName())->toBe('users.missing');
    expect(ltrim(getRoute($this->router)->getAction('uses'), '\\'))->toBe(RouteRegistrarControllerStub::class.'@missing');
});

test('can set route name using string backed enum', function () {
    $this->router->name(RouteNameEnum::UserIndex)->get('users', fn () => 'all-users');

    expect(getRoute($this->router)->getName())->toBe('users.index');
});

test('cannot set route name using integer backed enum', function () {

    $this->router->name(IntegerEnum::One)->get('users', fn () => 'all-users');
})->throws(InvalidArgumentException::class, 'Attribute [name] expects a string backed enum.');

test('can set route domain using string backed enum', function () {
    $this->router->domain(RouteDomainEnum::DashboardDomain)->get('users', fn () => 'all-users');

    expect(getRoute($this->router)->getDomain())->toBe('dashboard.myapp.com');
});

test('cannot set route domain using integer backed enum', function () {

    $this->router->domain(IntegerEnum::One)->get('users', fn () => 'all-users');
})->throws(InvalidArgumentException::class, 'Attribute [domain] expects a string backed enum.');

test('push middleware to group', function () {
    $this->router->middlewareGroup('web', []);
    $this->router->pushMiddlewareToGroup('web', 'test-middleware');

    expect($this->router->getMiddlewareGroups()['web'])->toEqual(['test-middleware']);
});

test('push middleware to group unregistered group', function () {
    $this->router->pushMiddlewareToGroup('web', 'test-middleware');

    expect($this->router->getMiddlewareGroups()['web'])->toEqual(['test-middleware']);
});

test('push middleware to group duplicated middleware', function () {
    $this->router->pushMiddlewareToGroup('web', 'test-middleware');
    $this->router->pushMiddlewareToGroup('web', 'test-middleware');

    expect($this->router->getMiddlewareGroups()['web'])->toEqual(['test-middleware']);
});

test('can remove middleware from group', function () {
    $this->router->pushMiddlewareToGroup('web', 'test-middleware');

    $this->router->removeMiddlewareFromGroup('web', 'test-middleware');

    expect($this->router->getMiddlewareGroups()['web'])->toBe([]);
});

test('can remove middleware from group not unregistered middleware', function () {
    $this->router->middlewareGroup('web', []);

    $this->router->removeMiddlewareFromGroup('web', 'different-test-middleware');

    expect($this->router->getMiddlewareGroups()['web'])->toBe([]);
});

test('can remove middleware from group unregistered group', function () {
    $this->router->removeMiddlewareFromGroup('web', ['test-middleware']);

    expect($this->router->getMiddlewareGroups())->toBe([]);
});

test('can register singleton', function () {
    $this->router->singleton('user', RouteRegistrarControllerStub::class);

    expect($this->router->getRoutes())->toHaveCount(3);

    expect($this->router->getRoutes()->hasNamedRoute('user.show'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.edit'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.update'))->toBeTrue();
});

test('can set route metadata on singleton', function () {
    $this->router->singleton('user', RouteRegistrarControllerStub::class)
        ->metadata(['head' => ['title' => 'User']]);

    expect($this->router->getRoutes()->getByName('user.show')->getMetadata('head'))->toBe(['title' => 'User']);
});

test('can register api singleton', function () {
    $this->router->apiSingleton('user', RouteRegistrarControllerStub::class);

    expect($this->router->getRoutes())->toHaveCount(2);

    expect($this->router->getRoutes()->hasNamedRoute('user.show'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.update'))->toBeTrue();
});

test('can set route metadata on api singleton', function () {
    $this->router->apiSingleton('user', RouteRegistrarControllerStub::class)
        ->metadata(['head' => ['title' => 'User']]);

    expect($this->router->getRoutes()->getByName('user.show')->getMetadata('head'))->toBe(['title' => 'User']);
});

test('can register creatable singleton', function () {
    $this->router->singleton('user', RouteRegistrarControllerStub::class)->creatable();

    expect($this->router->getRoutes())->toHaveCount(6);

    expect($this->router->getRoutes()->hasNamedRoute('user.create'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.store'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.show'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.edit'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.update'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.destroy'))->toBeTrue();
});

test('can register creatable api singleton', function () {
    $this->router->apiSingleton('user', RouteRegistrarControllerStub::class)->creatable();

    expect($this->router->getRoutes())->toHaveCount(4);

    expect($this->router->getRoutes()->hasNamedRoute('user.store'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.show'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.update'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.destroy'))->toBeTrue();
});

test('singleton creatable not destroyable', function () {
    $this->router->singleton('user', RouteRegistrarControllerStub::class)
        ->creatable()
        ->except('destroy');

    expect($this->router->getRoutes())->toHaveCount(5);

    expect($this->router->getRoutes()->hasNamedRoute('user.create'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.store'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.show'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.edit'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.update'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.destroy'))->toBeFalse();
});

test('api singleton creatable not destroyable', function () {
    $this->router->apiSingleton('user', RouteRegistrarControllerStub::class)
        ->creatable()
        ->except('destroy');

    expect($this->router->getRoutes())->toHaveCount(3);

    expect($this->router->getRoutes()->hasNamedRoute('user.store'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.show'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.update'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.destroy'))->toBeFalse();
});

test('singleton can be destroyable', function () {
    $this->router->singleton('user', RouteRegistrarControllerStub::class)
        ->destroyable();

    expect($this->router->getRoutes())->toHaveCount(4);

    expect($this->router->getRoutes()->hasNamedRoute('user.show'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.edit'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.update'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.destroy'))->toBeTrue();
});

test('api singleton can be destroyable', function () {
    $this->router->apiSingleton('user', RouteRegistrarControllerStub::class)
        ->destroyable();

    expect($this->router->getRoutes())->toHaveCount(3);

    expect($this->router->getRoutes()->hasNamedRoute('user.show'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.update'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.destroy'))->toBeTrue();
});

test('singleton can be only creatable', function () {
    $this->router->singleton('user', RouteRegistrarControllerStub::class)
        ->creatable()
        ->only('create', 'store');

    expect($this->router->getRoutes())->toHaveCount(2);

    expect($this->router->getRoutes()->hasNamedRoute('user.create'))->toBeTrue();
    expect($this->router->getRoutes()->hasNamedRoute('user.store'))->toBeTrue();
});

test('api singleton can be only creatable', function () {
    $this->router->apiSingleton('user', RouteRegistrarControllerStub::class)
        ->creatable()
        ->only('store');

    expect($this->router->getRoutes())->toHaveCount(1);

    expect($this->router->getRoutes()->hasNamedRoute('user.store'))->toBeTrue();
});

test('singleton doesnt allow including unsupported methods', function () {
    $this->router->singleton('post', RouteRegistrarControllerStub::class)
        ->only('index', 'store', 'create', 'destroy');

    expect($this->router->getRoutes())->toHaveCount(0);

    $this->router->apiSingleton('user', RouteRegistrarControllerStub::class)
        ->only('index', 'store', 'create', 'destroy');

    expect($this->router->getRoutes())->toHaveCount(0);
});

test('api singleton can include any singleton methods', function () {
    // This matches the behavior of the apiResource method.
    $this->router->apiSingleton('user', RouteRegistrarControllerStub::class)
        ->only('edit');

    expect($this->router->getRoutes())->toHaveCount(1);

    expect($this->router->getRoutes()->hasNamedRoute('user.edit'))->toBeTrue();
});

test('can set middleware for specified methods on registered singleton resource', function () {
    $this->router->singleton('users', RouteRegistrarControllerStub::class)
        ->creatable()
        ->destroyable()
        ->middleware('default')
        ->middlewareFor('show', RouteRegistrarMiddlewareStub::class)
        ->middlewareFor(['create', 'store'], 'one')
        ->middlewareFor(['edit'], ['one', 'two']);
    $this->router->getRoutes()->refreshNameLookups();

    expect($this->router->getRoutes()->getByName('users.create')->gatherMiddleware())->toEqual(['default', 'one']);
    expect($this->router->getRoutes()->getByName('users.store')->gatherMiddleware())->toEqual(['default', 'one']);
    expect($this->router->getRoutes()->getByName('users.show')->gatherMiddleware())->toEqual(['default', RouteRegistrarMiddlewareStub::class]);
    expect($this->router->getRoutes()->getByName('users.edit')->gatherMiddleware())->toEqual(['default', 'one', 'two']);
    expect($this->router->getRoutes()->getByName('users.update')->gatherMiddleware())->toEqual(['default']);
    expect($this->router->getRoutes()->getByName('users.destroy')->gatherMiddleware())->toEqual(['default']);

    $this->router->singleton('users', RouteRegistrarControllerStub::class)
        ->creatable()
        ->destroyable()
        ->middlewareFor('show', RouteRegistrarMiddlewareStub::class)
        ->middlewareFor(['create', 'store'], 'one')
        ->middlewareFor(['edit'], ['one', 'two'])
        ->middleware('default');
    $this->router->getRoutes()->refreshNameLookups();

    expect($this->router->getRoutes()->getByName('users.create')->gatherMiddleware())->toEqual(['one', 'default']);
    expect($this->router->getRoutes()->getByName('users.store')->gatherMiddleware())->toEqual(['one', 'default']);
    expect($this->router->getRoutes()->getByName('users.show')->gatherMiddleware())->toEqual([RouteRegistrarMiddlewareStub::class, 'default']);
    expect($this->router->getRoutes()->getByName('users.edit')->gatherMiddleware())->toEqual(['one', 'two', 'default']);
    expect($this->router->getRoutes()->getByName('users.update')->gatherMiddleware())->toEqual(['default']);
    expect($this->router->getRoutes()->getByName('users.destroy')->gatherMiddleware())->toEqual(['default']);
});

test('can set excluded middleware for specified methods on registered singleton resource', function () {
    $this->router->singleton('users', RouteRegistrarControllerStub::class)
        ->creatable()
        ->destroyable()
        ->withoutMiddleware('one')
        ->withoutMiddlewareFor('show', 'two')
        ->withoutMiddlewareFor(['create', 'store'], 'three')
        ->withoutMiddlewareFor(['edit'], ['four', 'five']);

    expect($this->router->getRoutes()->getByName('users.create')->excludedMiddleware())->toEqual(['one', 'three']);
    expect($this->router->getRoutes()->getByName('users.store')->excludedMiddleware())->toEqual(['one', 'three']);
    expect($this->router->getRoutes()->getByName('users.show')->excludedMiddleware())->toEqual(['one', 'two']);
    expect($this->router->getRoutes()->getByName('users.edit')->excludedMiddleware())->toEqual(['one', 'four', 'five']);
    expect($this->router->getRoutes()->getByName('users.update')->excludedMiddleware())->toEqual(['one']);
    expect($this->router->getRoutes()->getByName('users.destroy')->excludedMiddleware())->toEqual(['one']);
});

function getRoute(Router $router)
{
    return last($router->getRoutes()->get());
}

function seeMiddleware(Router $router, $middleware)
{
    expect(getRoute($router)->middleware()[0])->toEqual($middleware);
}

function seeResponse(Router $router, $content, Request $request)
{
    $route = getRoute($router);

    expect($route->matches($request))->toBeTrue();

    expect($route->bind($request)->run())->toEqual($content);
}

class RouteRegistrarControllerStub
{
    public function index()
    {
        return 'controller';
    }

    public function destroy()
    {
        return 'deleted';
    }
}

class InvokableRouteRegistrarControllerStub
{
    public function __invoke()
    {
        return 'controller';
    }
}

class RouteRegistrarMiddlewareStub
{
    //
}
