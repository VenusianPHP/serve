<?php

namespace Venusian\Serve\Tests\Routing;

use Attribute;
use Closure;
use DateTime;
use Exception;
use Mariner\Auth\Middleware\Authenticate;
use Mariner\Auth\Middleware\Authorize;
use Voyager\Config\Repository;
use Voyager\Vessel\ControlPanel;
use Mariner\Contracts\Routing\Registrar;
use Mariner\Contracts\NutsAndBolts\Responsable;
use Voyager\Database\Instrument\Model;
use Voyager\Database\Instrument\ModelNotFoundException;
use Voyager\Signals\SignalDispatcher;
use Mariner\Http\Exceptions\HttpResponseException;
use Mariner\Http\JsonResponse;
use Mariner\Http\RedirectResponse;
use Mariner\Http\Request;
use Mariner\Http\Response;
use Mariner\Routing\CallableDispatcher;
use Mariner\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Mariner\Routing\Contracts\ControllerDispatcher as ControllerDispatcherContract;
use Mariner\Routing\Controller;
use Mariner\Routing\ControllerDispatcher;
use Mariner\Routing\Events\PreparingResponse;
use Mariner\Routing\Events\ResponsePrepared;
use Mariner\Routing\Events\Routing;
use Mariner\Routing\Exceptions\UrlGenerationException;
use Mariner\Routing\Middleware\SubstituteBindings;
use Mariner\Routing\ResourceRegistrar;
use Mariner\Contracts\Routing\UrlRoutable;
use Mariner\Routing\Concerns\BindsToRoutes;
use Mariner\Routing\Route;
use Mariner\Routing\RouteCollection;
use Mariner\Routing\RouteGroup;
use Mariner\Routing\RouteState;
use Mariner\Routing\Router;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Mariner\Routing\UrlGenerator;
use Voyager\NutsAndBolts\Str;
use Venusian\Serve\Tests\Routing\Fixtures\CategoryBackedEnum;
use LogicException;
use stdClass;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use UnexpectedValueException;

require_once __DIR__.'/Fixtures/Enums.php';

afterEach(function () {
    ResourceRegistrar::singularParameters();
    ResourceRegistrar::setParameters();
    ResourceRegistrar::verbs(['create' => 'create', 'edit' => 'edit']);

});

test('basic dispatching of routes', function () {
    $router = getRouter();
    $router->get('foo/bar', function () {
        return 'hello';
    });
    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('hello');

    $router = getRouter();
    $router->get('foo/bar', function () {
        throw new HttpResponseException(new Response('hello'));
    });
    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('hello');

    $router = getRouter();
    $router->get('foo/bar', ['domain' => 'api.{name}.bar', function ($name) {
        return $name;
    }]);
    $router->get('foo/bar', ['domain' => 'api.{name}.baz', function ($name) {
        return $name;
    }]);
    expect($router->dispatch(Request::create('http://api.taylor.bar/foo/bar', 'GET'))->getContent())->toBe('taylor');
    expect($router->dispatch(Request::create('http://api.dayle.baz/foo/bar', 'GET'))->getContent())->toBe('dayle');

    $router = getRouter();
    $router->get('foo/{age}', ['domain' => 'api.{name}.bar', function ($name, $age) {
        return $name.$age;
    }]);
    expect($router->dispatch(Request::create('http://api.taylor.bar/foo/25', 'GET'))->getContent())->toBe('taylor25');

    $router = getRouter();
    $router->get('foo/bar', function () {
        return 'hello';
    });
    $router->post('foo/bar', function () {
        return 'post hello';
    });
    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('hello');
    expect($router->dispatch(Request::create('foo/bar', 'POST'))->getContent())->toBe('post hello');

    $router = getRouter();
    $router->get('foo/{bar}', function ($name) {
        return $name;
    });
    expect($router->dispatch(Request::create('foo/taylor', 'GET'))->getContent())->toBe('taylor');

    $router = getRouter();
    $router->get('foo/{bar}/{baz?}', function ($name, $age = 25) {
        return $name.$age;
    });
    expect($router->dispatch(Request::create('foo/taylor', 'GET'))->getContent())->toBe('taylor25');

    $router = getRouter();
    $router->get('foo/{name}/boom/{age?}/{location?}', function ($name, $age = 25, $location = 'AR') {
        return $name.$age.$location;
    });
    expect($router->dispatch(Request::create('foo/taylor/boom/30', 'GET'))->getContent())->toBe('taylor30AR');

    $router = getRouter();
    $router->get('{bar}/{baz?}', function ($name, $age = 25) {
        return $name.$age;
    });
    expect($router->dispatch(Request::create('taylor', 'GET'))->getContent())->toBe('taylor25');

    $router = getRouter();
    $router->get('{baz?}', function ($age = 25) {
        return $age;
    });
    expect($router->dispatch(Request::create('/', 'GET'))->getContent())->toBe('25');
    expect($router->dispatch(Request::create('30', 'GET'))->getContent())->toBe('30');

    $router = getRouter();
    $router->get('{foo?}/{baz?}', ['as' => 'foo', function ($name = 'taylor', $age = 25) {
        return $name.$age;
    }]);
    expect($router->dispatch(Request::create('/', 'GET'))->getContent())->toBe('taylor25');
    expect($router->dispatch(Request::create('fred', 'GET'))->getContent())->toBe('fred25');
    expect($router->dispatch(Request::create('fred/30', 'GET'))->getContent())->toBe('fred30');
    expect($router->currentRouteNamed('foo'))->toBeTrue();
    expect($router->currentRouteNamed('fo*'))->toBeTrue();
    expect($router->is('foo'))->toBeTrue();
    expect($router->is('foo', 'bar'))->toBeTrue();
    expect($router->is('bar'))->toBeFalse();

    $router = getRouter();
    $router->get('foo/{file}', function ($file) {
        return $file;
    });
    expect($router->dispatch(Request::create('http://test.com/foo/oxygen%2520', 'GET'))->getContent())->toBe('oxygen%20');

    $router = getRouter();
    $router->patch('foo/bar', ['as' => 'foo', function () {
        return 'bar';
    }]);
    expect($router->dispatch(Request::create('foo/bar', 'PATCH'))->getContent())->toBe('bar');
    expect($router->currentRouteName())->toBe('foo');

    $router = getRouter();
    $router->get('foo/bar', function () {
        return 'hello';
    });
    expect($router->dispatch(Request::create('foo/bar', 'HEAD'))->getContent())->toBeEmpty();

    $router = getRouter();
    $router->any('foo/bar', function () {
        return 'hello';
    });
    expect($router->dispatch(Request::create('foo/bar', 'HEAD'))->getContent())->toBeEmpty();

    $router = getRouter();
    $router->get('foo/bar', function () {
        return 'first';
    });
    $router->get('foo/bar', function () {
        return 'second';
    });
    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('second');

    $router = getRouter();
    $router->get('foo/bar/åαф', function () {
        return 'hello';
    });
    expect($router->dispatch(Request::create('foo/bar/%C3%A5%CE%B1%D1%84', 'GET'))->getContent())->toBe('hello');

    $router = getRouter();
    $router->get('foo/bar', ['boom' => 'auth', function () {
        return 'closure';
    }]);
    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('closure');
});

test('not modified response is properly returned', function () {
    $router = getRouter();
    $router->get('test', function () {
        return (new SymfonyResponse('test', 304, ['foo' => 'bar']))->setLastModified(new DateTime);
    });

    $response = $router->dispatch(Request::create('test', 'GET'));
    expect($response->getStatusCode())->toBe(304);
    expect($response->getContent())->toBeEmpty();
    expect($response->headers->get('foo'))->toBe('bar');
    expect($response->getLastModified())->toBeNull();
});

test('closure middleware', function () {
    $router = getRouter();
    $middleware = function ($request, $next) {
        return 'caught';
    };
    $router->get('foo/bar', ['middleware' => $middleware, function () {
        return 'hello';
    }]);
    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('caught');
});

test('middleware can be skipped', function () {
    $router = getRouter();
    $router->aliasMiddleware('web', RoutingTestMiddlewareGroupTwo::class);

    $router->get('foo/bar', ['middleware' => 'web', function () {
        return 'hello';
    }])->withoutMiddleware(RoutingTestMiddlewareGroupTwo::class);

    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('hello');
});

test('middleware can be skipped from resources', function () {
    $router = getRouter();
    $router->aliasMiddleware('web', RoutingTestMiddlewareGroupTwo::class);

    $router->resource('foo', RouteTestControllerMiddlewareGroupStub::class)
        ->middleware('web')
        ->withoutMiddleware(RoutingTestMiddlewareGroupTwo::class);

    expect($router->dispatch(Request::create('foo', 'GET'))->getContent())->toBe('Hello World');
});

test('middleware works if controller throws http response exception', function () {
    // Before calling controller
    $router = getRouter();
    $middleware = function ($request, $next) {
        return 'caught';
    };
    $router->get('foo/bar', ['middleware' => $middleware, function () {
        throw new HttpResponseException(new Response('hello'));
    }]);
    $response = $router->dispatch(Request::create('foo/bar', 'GET'))->getContent();
    expect($response)->toBe('caught');

    // After calling controller
    $router = getRouter();

    $response = new Response('hello');

    $middleware = function ($request, $next) use ($response) {
        expect($next($request))->toBe($response);

        return new Response($response->getContent().' caught');
    };
    $router->get('foo/bar', ['middleware' => $middleware, function () use ($response) {
        throw new HttpResponseException($response);
    }]);

    $response = $router->dispatch(Request::create('foo/bar', 'GET'))->getContent();
    expect($response)->toBe('hello caught');
});

test('returns response when middleware returns responsable', function () {
    $router = getRouter();
    $router->get('foo/bar', [
        'uses' => RouteTestClosureMiddlewareController::class.'@index',
        'middleware' => ['foo', 'bar', 'baz'],
    ]);
    $router->aliasMiddleware('foo', function ($request, $next) {
        return $next($request);
    });
    $router->aliasMiddleware('bar', function ($request, $next) {
        return new ResponsableResponse;
    });
    $router->aliasMiddleware('baz', function ($request, $next) {
        return $next($request);
    });
    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('bar');
});

test('defined closure middleware', function () {
    $router = getRouter();
    $router->get('foo/bar', ['middleware' => 'foo', function () {
        return 'hello';
    }]);
    $router->aliasMiddleware('foo', function ($request, $next) {
        return 'caught';
    });
    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('caught');
});

test('controller closure middleware', function () {
    $router = getRouter();
    $router->get('foo/bar', [
        'uses' => RouteTestClosureMiddlewareController::class.'@index',
        'middleware' => 'foo',
    ]);
    $router->aliasMiddleware('foo', function ($request, $next) {
        $request['foo-middleware'] = 'foo-middleware';

        return $next($request);
    });

    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('index-foo-middleware-controller-closure');
});

test('fluent routing', function () {

    $router = getRouter();
    $router->get('foo/bar')->uses(function () {
        return 'hello';
    });
    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('hello');
    $router->post('foo/bar')->uses(function () {
        return 'hello';
    });
    expect($router->dispatch(Request::create('foo/bar', 'POST'))->getContent())->toBe('hello');
    $router->get('foo/bar')->uses(function () {
        return 'middleware';
    })->middleware(RouteTestControllerMiddleware::class);
    expect($router->dispatch(Request::create('foo/bar'))->getContent())->toBe('middleware');
    expect($router->getCurrentRoute()->middleware())->toContain(RouteTestControllerMiddleware::class);
    $router->get('foo/bar');
    $router->dispatch(Request::create('foo/bar', 'GET'));
})->throws(LogicException::class, 'Route for [foo/bar] has no action.');

test('fluent routing with controller action', function () {
    $router = getRouter();
    $router->get('foo/bar')->uses(RouteTestControllerStub::class.'@index');
    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('Hello World');

    $router = getRouter();
    $router->group(['namespace' => 'App'], function ($router) {
        $router->get('foo/bar')->uses(RouteTestControllerStub::class.'@index');
    });
    $action = $router->getRoutes()->getRoutes()[0]->getAction();
    expect($action['controller'])->toBe('App\\'.RouteTestControllerStub::class.'@index');
});

test('middleware groups', function () {
    unset($_SERVER['__middleware.group']);
    $router = getRouter();
    $router->get('foo/bar', ['middleware' => 'web', function () {
        return 'hello';
    }]);

    $router->aliasMiddleware('two', RoutingTestMiddlewareGroupTwo::class);
    $router->middlewareGroup('web', [RoutingTestMiddlewareGroupOne::class, 'two:taylor']);

    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('caught taylor');
    expect($_SERVER['__middleware.group'])->toBeTrue();

    unset($_SERVER['__middleware.group']);
});

test('middleware groups can reference other groups', function () {
    unset($_SERVER['__middleware.group']);
    $router = getRouter();
    $router->get('foo/bar', ['middleware' => 'web', function () {
        return 'hello';
    }]);

    $router->aliasMiddleware('two', RoutingTestMiddlewareGroupTwo::class);
    $router->middlewareGroup('first', ['two:abigail']);
    $router->middlewareGroup('web', [RoutingTestMiddlewareGroupOne::class, 'first']);

    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('caught abigail');
    expect($_SERVER['__middleware.group'])->toBeTrue();

    unset($_SERVER['__middleware.group']);
});

test('middleware groups cannot reference itself', function () {

    $router = getRouter();
    $router->get('foo/bar', ['middleware' => 'web', function () {
        return 'hello';
    }]);

    $router->middlewareGroup('web', ['web']);

    $router->dispatch(Request::create('foo/bar', 'GET'));
})->throws(LogicException::class, '[web] middleware group is referencing itself.');

test('fluent route naming within agroup', function () {
    $router = getRouter();
    $router->group(['as' => 'foo.'], function () use ($router) {
        $router->get('bar', function () {
            return 'bar';
        })->name('bar');
    });
    expect($router->dispatch(Request::create('bar', 'GET'))->getContent())->toBe('bar');
    expect($router->currentRouteName())->toBe('foo.bar');
});

test('route get action', function () {
    $router = getRouter();

    $route = $router->get('foo', function () {
        return 'foo';
    })->name('foo');

    expect($route->getAction())->toBeArray();
    expect($route->getAction())->toHaveKey('as');
    expect($route->getAction('as'))->toBe('foo');
    expect($route->getAction('unknown_property'))->toBeNull();
});

test('route get controller class', function () {
    $router = getRouter();

    $controllerRoute = $router->get('foo/bar')->uses(RouteTestControllerStub::class.'@index');
    $closureRoute = $router->get('foo', function () {
        return 'foo';
    });

    expect($controllerRoute->getControllerClass())->toBe(RouteTestControllerStub::class);
    expect($closureRoute->getControllerClass())->toBeNull();
});

test('resolving binding parameters', function () {
    $router = getRouter();

    $route = $router->get('foo/{bar:slug}', function () {
        return 'foo';
    })->name('foo');

    expect($route->bindingFieldFor('bar'))->toBe('slug');

    $route = $router->get('foo/{bar:slug}/{baz}', function () {
        return 'foo';
    })->name('foo');

    expect($route->bindingFieldFor('baz'))->toBeNull();
});

test('macro', function () {
    $router = getRouter();
    $router->macro('webhook', function () use ($router) {
        $router->match(['GET', 'POST'], 'webhook', function () {
            return 'OK';
        });
    });
    $router->webhook();
    expect($router->dispatch(Request::create('webhook', 'GET'))->getContent())->toBe('OK');
    expect($router->dispatch(Request::create('webhook', 'POST'))->getContent())->toBe('OK');
});

test('route macro', function () {
    $router = getRouter();

    Route::macro('breadcrumb', function ($breadcrumb) {
        $this->action['breadcrumb'] = $breadcrumb;

        return $this;
    });

    $router->get('foo', function () {
        return 'bar';
    })->breadcrumb('fooBreadcrumb')->name('foo');

    $router->getRoutes()->refreshNameLookups();

    expect($router->getRoutes()->getByName('foo')->getAction()['breadcrumb'])->toBe('fooBreadcrumb');
});

test('classes can be injected into routes', function () {
    unset($_SERVER['__test.route_inject']);
    $router = getRouter();
    $router->get('foo/{var}', function (stdClass $foo, $var) {
        $_SERVER['__test.route_inject'] = func_get_args();

        return 'hello';
    });

    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('hello');
    expect($_SERVER['__test.route_inject'][0])->toBeInstanceOf(stdClass::class);
    expect($_SERVER['__test.route_inject'][1])->toBe('bar');

    unset($_SERVER['__test.route_inject']);
});

test('null values can be injected into routes', function () {
    $container = new ControlPanel;
    $router = new Router(new SignalDispatcher, $container);
    $container->registerInstance(Registrar::class, $router);

    $container->bind(RoutingTestUserModel::class, function () {
    });
    $container->bind(CallableDispatcherContract::class, fn ($app) => new CallableDispatcher($app));

    $router->get('foo/{team}/{post}', [
        'middleware' => SubstituteBindings::class,
        'uses' => function (?RoutingTestUserModel $userFromContainer, RoutingTestTeamModel $team, $postId) {
            expect($userFromContainer)->toBeNull();
            expect($team)->toBeInstanceOf(RoutingTestTeamModel::class);
            expect($team->value)->toBe('bar');
            expect($postId)->toBe('baz');
        },
    ]);
    $router->dispatch(Request::create('foo/bar/baz', 'GET'))->getContent();
});

test('query routes are dispatched', function () {
    $router = getRouter();
    $router->query('products/search', function () use ($router) {
        return $router->getCurrentRequest()->input('name');
    });

    $request = Request::create('products/search', 'QUERY', ['name' => 'taylor']);

    expect($router->dispatch($request)->getContent())->toBe('taylor');
    expect($router->getRoutes()->getRoutes()[0]->methods())->toBe(['QUERY']);
});

test('query routes receive json body', function () {
    $router = getRouter();
    $router->query('products/search', function () use ($router) {
        return $router->getCurrentRequest()->input('filters.name');
    });

    $request = Request::create('products/search', 'QUERY', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['filters' => ['name' => 'taylor']]));

    expect($router->dispatch($request)->getContent())->toBe('taylor');
});

test('any routes respond to query requests', function () {
    $router = getRouter();
    $router->any('foo', function () {
        return 'hello';
    });

    expect($router->dispatch(Request::create('foo', 'QUERY'))->getContent())->toBe('hello');
});

test('options responses are generated by default', function () {
    $router = getRouter();
    $router->get('foo/bar', function () {
        return 'hello';
    });
    $router->post('foo/bar', function () {
        return 'hello';
    });
    $response = $router->dispatch(Request::create('foo/bar', 'OPTIONS'));

    expect($response->getStatusCode())->toEqual(200);
    expect($response->headers->get('Allow'))->toBe('GET,HEAD,POST');
});

test('head dispatcher', function () {
    $router = getRouter();
    $router->match(['GET', 'POST'], 'foo', function () {
        return 'bar';
    });

    $response = $router->dispatch(Request::create('foo', 'OPTIONS'));
    expect($response->getStatusCode())->toEqual(200);
    expect($response->headers->get('Allow'))->toBe('GET,HEAD,POST');

    $response = $router->dispatch(Request::create('foo', 'HEAD'));
    expect($response->getStatusCode())->toEqual(200);
    expect($response->getContent())->toBeEmpty();

    $router = getRouter();
    $router->match(['GET'], 'foo', function () {
        return 'bar';
    });

    $response = $router->dispatch(Request::create('foo', 'OPTIONS'));
    expect($response->getStatusCode())->toEqual(200);
    expect($response->headers->get('Allow'))->toBe('GET,HEAD');

    $router = getRouter();
    $router->match(['POST'], 'foo', function () {
        return 'bar';
    });

    $response = $router->dispatch(Request::create('foo', 'OPTIONS'));
    expect($response->getStatusCode())->toEqual(200);
    expect($response->headers->get('Allow'))->toBe('POST');
});

test('non greedy matches', function () {
    $route = new Route('GET', 'images/{id}.{ext}', function () {
        //
    });

    $request1 = Request::create('images/1.png', 'GET');
    expect($route->matches($request1))->toBeTrue();
    $route->bind($request1);
    expect($route->hasParameter('id'))->toBeTrue();
    expect($route->hasParameter('foo'))->toBeFalse();
    expect((string) $route->parameter('id'))->toBe('1');
    expect($route->parameter('ext'))->toBe('png');

    $request2 = Request::create('images/12.png', 'GET');
    expect($route->matches($request2))->toBeTrue();
    $route->bind($request2);
    expect($route->parameter('id'))->toBe('12');
    expect($route->parameter('ext'))->toBe('png');

    // Test parameter() default value
    $route = new Route('GET', 'foo/{foo?}', function () {
        //
    });

    $request3 = Request::create('foo', 'GET');
    expect($route->matches($request3))->toBeTrue();
    $route->bind($request3);
    expect($route->parameter('foo', 'bar'))->toBe('bar');
});

test('has parameters', function () {
    $route = new Route('GET', 'images/{id}.{ext}', function () {
        //
    });
    $request1 = Request::create('images/1.png', 'GET');
    expect($route->hasParameters())->toBeFalse();
    expect($route->matches($request1))->toBeTrue();
    $route->bind($request1);
    expect($route->hasParameters())->toBeTrue();
});

test('forget parameter', function () {
    $route = new Route('GET', 'images/{id}.{ext}', function () {
        //
    });
    $request1 = Request::create('images/1.png', 'GET');
    $route->bind($request1);
    expect($route->hasParameter('id'))->toBeTrue();
    expect($route->hasParameter('ext'))->toBeTrue();
    $route->forgetParameter('id');
    expect($route->hasParameter('id'))->toBeFalse();
    expect($route->hasParameter('ext'))->toBeTrue();
});

test('parameter names', function () {
    $route = new Route('GET', 'images/{id}.{ext}', function () {
        //
    });
    expect($route->parameterNames())->toBe(['id', 'ext']);

    $route = new Route('GET', 'foo/{bar?}', function () {
        //
    });
    expect($route->parameterNames())->toBe(['bar']);

    $route = new Route('GET', '/', function () {
        //
    });
    expect($route->parameterNames())->toBe([]);
});

test('parameters without nulls', function () {
    $route = new Route('GET', 'users/{id?}/{name?}/', function () {
        //
    });
    $request1 = Request::create('users/12/amir', 'GET');
    $route->bind($request1);
    expect($route->parametersWithoutNulls())->toBe(['id' => '12', 'name' => 'amir']);

    $route = new Route('GET', 'users/{id?}/{name?}/', function () {
        //
    });
    $request1 = Request::create('users/12', 'GET');
    $route->bind($request1);
    expect($route->parametersWithoutNulls())->toBe(['id' => '12']);

    $route = new Route('GET', 'users/{id?}/{name?}/', function () {
        //
    });
    $request1 = Request::create('users/', 'GET');
    $route->bind($request1);
    expect($route->parametersWithoutNulls())->toBe([]);
});

test('route parameters default value', function () {
    $router = getRouter();
    $router->get('foo/{bar?}', ['uses' => RouteTestControllerWithParameterStub::class.'@returnParameter'])->defaults('bar', 'foo');
    expect($router->dispatch(Request::create('foo', 'GET'))->getContent())->toBe('foo');

    $router->get('foo/{bar?}', ['uses' => RouteTestControllerWithParameterStub::class.'@returnParameter'])->defaults('bar', 'foo');
    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('bar');

    $router->get('foo/{bar?}', function ($bar = '') {
        return $bar;
    })->defaults('bar', 'foo');
    expect($router->dispatch(Request::create('foo', 'GET'))->getContent())->toBe('foo');
});

test('controller call action method parameters', function () {
    $router = getRouter();

    // Has one argument but receives two
    unset($_SERVER['__test.controller_callAction_parameters']);
    $router->get(($str = Str::random()).'/{one}/{two}', RouteTestAnotherControllerWithParameterStub::class.'@oneArgument');
    $router->dispatch(Request::create($str.'/one/two', 'GET'));
    expect($_SERVER['__test.controller_callAction_parameters'])->toEqual(['one' => 'one', 'two' => 'two']);

    // Has two arguments and receives two
    unset($_SERVER['__test.controller_callAction_parameters']);
    $router->get(($str = Str::random()).'/{one}/{two}', RouteTestAnotherControllerWithParameterStub::class.'@twoArguments');
    $router->dispatch(Request::create($str.'/one/two', 'GET'));
    expect($_SERVER['__test.controller_callAction_parameters'])->toEqual(['one' => 'one', 'two' => 'two']);

    // Has two arguments but with different names from the ones passed from the route
    unset($_SERVER['__test.controller_callAction_parameters']);
    $router->get(($str = Str::random()).'/{one}/{two}', RouteTestAnotherControllerWithParameterStub::class.'@differentArgumentNames');
    $router->dispatch(Request::create($str.'/one/two', 'GET'));
    expect($_SERVER['__test.controller_callAction_parameters'])->toEqual(['one' => 'one', 'two' => 'two']);

    // Has two arguments with same name but argument order is reversed
    unset($_SERVER['__test.controller_callAction_parameters']);
    $router->get(($str = Str::random()).'/{one}/{two}', RouteTestAnotherControllerWithParameterStub::class.'@reversedArguments');
    $router->dispatch(Request::create($str.'/one/two', 'GET'));
    expect($_SERVER['__test.controller_callAction_parameters'])->toEqual(['one' => 'one', 'two' => 'two']);

    // No route parameters while method has parameters
    unset($_SERVER['__test.controller_callAction_parameters']);
    $router->get(($str = Str::random()).'', RouteTestAnotherControllerWithParameterStub::class.'@oneArgument');
    $router->dispatch(Request::create($str, 'GET'));
    expect($_SERVER['__test.controller_callAction_parameters'])->toBe([]);

    // With model bindings
    unset($_SERVER['__test.controller_callAction_parameters']);
    $router->get(($str = Str::random()).'/{user}/{defaultNull?}/{team?}', [
        'middleware' => SubstituteBindings::class,
        'uses' => RouteTestAnotherControllerWithParameterStub::class.'@withModels',
    ]);
    $router->dispatch(Request::create($str.'/1', 'GET'));

    $values = array_values($_SERVER['__test.controller_callAction_parameters']);

    expect($values[0])->toBeInstanceOf(Request::class);
    expect($values[1]->value)->toEqual(1);
    expect($values[2])->toBeNull();
    expect($values[3])->toBeNull();
});

test('leading param doesnt receive forward slash on empty path', function () {
    $router = getRouter();
    $outer_one = 'abc1234'; // a string that is not one we're testing
    $router->get('{one?}', [
        'uses' => function ($one = null) use (&$outer_one) {
            $outer_one = $one;

            return $one;
        },
        'where' => ['one' => '(.+)'],
    ]);

    expect($router->dispatch(Request::create(''))->getContent())->toBe('');
    expect($outer_one)->toBeNull();
    // Expects: '' ($one === null)
    // Actual: '/' ($one === '/')

    expect($router->dispatch(Request::create('/foo', 'GET'))->getContent())->toBe('foo');
    expect($router->dispatch(Request::create('/foo/bar/baz', 'GET'))->getContent())->toBe('foo/bar/baz');
});

test('routes dont match non matching paths with leading optionals', function () {

    $router = getRouter();
    $router->get('{baz?}', function ($age = 25) {
        return $age;
    });
    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('25');
})->throws(NotFoundHttpException::class);

test('routes dont match non matching domain', function () {

    $router = getRouter();
    $router->get('foo/bar', ['domain' => 'api.foo.bar', function () {
        return 'hello';
    }]);
    expect($router->dispatch(Request::create('http://api.baz.boom/foo/bar', 'GET'))->getContent())->toBe('hello');
})->throws(NotFoundHttpException::class);

test('route domain registration', function () {
    $router = getRouter();
    $router->get('/foo/bar')->domain('api.foo.bar')->uses(function () {
        return 'hello';
    });
    expect($router->dispatch(Request::create('http://api.foo.bar/foo/bar', 'GET'))->getContent())->toBe('hello');
});

test('matches method against requests', function () {
    // Basic
    $request = Request::create('foo/bar', 'GET');
    $route = new Route('GET', 'foo/{bar}', function () {
        //
    });
    expect($route->matches($request))->toBeTrue();

    $request = Request::create('foo/bar', 'GET');
    $route = new Route('GET', 'foo', function () {
        //
    });
    expect($route->matches($request))->toBeFalse();

    // Method checks
    $request = Request::create('foo/bar', 'GET');
    $route = new Route('GET', 'foo/{bar}', function () {
        //
    });
    expect($route->matches($request))->toBeTrue();

    $request = Request::create('foo/bar', 'POST');
    $route = new Route('GET', 'foo', function () {
        //
    });
    expect($route->matches($request))->toBeFalse();

    // Domain checks
    $request = Request::create('http://something.foo.com/foo/bar', 'GET');
    $route = new Route('GET', 'foo/{bar}', ['domain' => '{foo}.foo.com', function () {
        //
    }]);
    expect($route->matches($request))->toBeTrue();

    $request = Request::create('http://something.bar.com/foo/bar', 'GET');
    $route = new Route('GET', 'foo/{bar}', ['domain' => '{foo}.foo.com', function () {
        //
    }]);
    expect($route->matches($request))->toBeFalse();

    // HTTPS checks
    $request = Request::create('https://foo.com/foo/bar', 'GET');
    $route = new Route('GET', 'foo/{bar}', ['https', function () {
        //
    }]);
    expect($route->matches($request))->toBeTrue();

    $request = Request::create('https://foo.com/foo/bar', 'GET');
    $route = new Route('GET', 'foo/{bar}', ['https', 'baz' => true, function () {
        //
    }]);
    expect($route->matches($request))->toBeTrue();

    $request = Request::create('http://foo.com/foo/bar', 'GET');
    $route = new Route('GET', 'foo/{bar}', ['https', function () {
        //
    }]);
    expect($route->matches($request))->toBeFalse();

    // HTTP checks
    $request = Request::create('https://foo.com/foo/bar', 'GET');
    $route = new Route('GET', 'foo/{bar}', ['http', function () {
        //
    }]);
    expect($route->matches($request))->toBeFalse();

    $request = Request::create('http://foo.com/foo/bar', 'GET');
    $route = new Route('GET', 'foo/{bar}', ['http', function () {
        //
    }]);
    expect($route->matches($request))->toBeTrue();

    $request = Request::create('http://foo.com/foo/bar', 'GET');
    $route = new Route('GET', 'foo/{bar}', ['baz' => true, function () {
        //
    }]);
    expect($route->matches($request))->toBeTrue();
});

test('where patterns properly filter', function () {
    $request = Request::create('foo/123', 'GET');
    $route = new Route('GET', 'foo/{bar}', function () {
        //
    });
    $route->where('bar', '[0-9]+');
    expect($route->matches($request))->toBeTrue();

    $request = Request::create('foo/123abc', 'GET');
    $route = new Route('GET', 'foo/{bar}', function () {
        //
    });
    $route->where('bar', '[0-9]+');
    expect($route->matches($request))->toBeFalse();

    $request = Request::create('foo/123abc', 'GET');
    $route = new Route('GET', 'foo/{bar}', ['where' => ['bar' => '[0-9]+'], function () {
        //
    }]);
    $route->where('bar', '[0-9]+');
    expect($route->matches($request))->toBeFalse();

    $request = Request::create('foo/123', 'GET');
    $route = new Route('GET', 'foo/{bar}', ['where' => ['bar' => '123|456'], function () {
        //
    }]);
    $route->where('bar', '123|456');
    expect($route->matches($request))->toBeTrue();

    $request = Request::create('foo/123abc', 'GET');
    $route = new Route('GET', 'foo/{bar}', ['where' => ['bar' => '123|456'], function () {
        //
    }]);
    $route->where('bar', '123|456');
    expect($route->matches($request))->toBeFalse();

    // Optional
    $request = Request::create('foo/123', 'GET');
    $route = new Route('GET', 'foo/{bar?}', function () {
        //
    });
    $route->where('bar', '[0-9]+');
    expect($route->matches($request))->toBeTrue();

    $request = Request::create('foo/123', 'GET');
    $route = new Route('GET', 'foo/{bar?}', ['where' => ['bar' => '[0-9]+'], function () {
        //
    }]);
    $route->where('bar', '[0-9]+');
    expect($route->matches($request))->toBeTrue();

    $request = Request::create('foo/123', 'GET');
    $route = new Route('GET', 'foo/{bar?}/{baz?}', function () {
        //
    });
    $route->where('bar', '[0-9]+');
    expect($route->matches($request))->toBeTrue();

    $request = Request::create('foo/123/foo', 'GET');
    $route = new Route('GET', 'foo/{bar?}/{baz?}', function () {
        //
    });
    $route->where('bar', '[0-9]+');
    expect($route->matches($request))->toBeTrue();

    $request = Request::create('foo/123abc', 'GET');
    $route = new Route('GET', 'foo/{bar?}', function () {
        //
    });
    $route->where('bar', '[0-9]+');
    expect($route->matches($request))->toBeFalse();

    // Conditional
    $route = new Route('GET', '{subdomain}.awesome.test', function () {
        //
    });

    $route->when(true, function ($route) {
        $route->whereIn('subdomain', [
            'one',
            'two',
        ]);
    });

    $request = Request::create('test.awesome.test', 'GET');
    expect($route->matches($request))->toBeFalse();

    $request = Request::create('one.awesome.test', 'GET');
    expect($route->matches($request))->toBeTrue();
});

test('route prefix parameter parsing', function () {
    $route = new Route('GET', '/foo', ['prefix' => 'profiles/{user:username}/portfolios', 'uses' => function () {
        //
    }]);

    expect($route->uri())->toBe('profiles/{user}/portfolios/foo');
});

test('dot does not match everything', function () {
    $route = new Route('GET', 'images/{id}.{ext}', function () {
        //
    });

    $request1 = Request::create('images/1.png', 'GET');
    expect($route->matches($request1))->toBeTrue();
    $route->bind($request1);
    expect((string) $route->parameter('id'))->toBe('1');
    expect($route->parameter('ext'))->toBe('png');

    $request2 = Request::create('images/12.png', 'GET');
    expect($route->matches($request2))->toBeTrue();
    $route->bind($request2);
    expect($route->parameter('id'))->toBe('12');
    expect($route->parameter('ext'))->toBe('png');
});

test('route binding', function () {
    $router = getRouter();
    $router->get('foo/{bar}', ['middleware' => SubstituteBindings::class, 'uses' => function ($name) {
        return $name;
    }]);
    $router->bind('bar', function ($value) {
        return strtoupper($value);
    });
    expect($router->dispatch(Request::create('foo/taylor', 'GET'))->getContent())->toBe('TAYLOR');
});

test('route class binding', function () {
    $router = getRouter();
    $router->get('foo/{bar}', ['middleware' => SubstituteBindings::class, 'uses' => function ($name) {
        return $name;
    }]);
    $router->bind('bar', RouteBindingStub::class);
    expect($router->dispatch(Request::create('foo/taylor', 'GET'))->getContent())->toBe('TAYLOR');
});

test('route class method binding', function () {
    $router = getRouter();
    $router->get('foo/{bar}', ['middleware' => SubstituteBindings::class, 'uses' => function ($name) {
        return $name;
    }]);
    $router->bind('bar', RouteBindingStub::class.'@find');
    expect($router->dispatch(Request::create('foo/Dragon', 'GET'))->getContent())->toBe('dragon');
});

test('middleware priority sorting', function () {
    $middleware = [
        Placeholder1::class,
        SubstituteBindings::class,
        Placeholder2::class,
        Authenticate::class,
        ExampleMiddleware::class,
        Placeholder3::class,
    ];

    $router = getRouter();

    $router->middlewarePriority = [ExampleMiddlewareContract::class, Authenticate::class, SubstituteBindings::class, Authorize::class];

    $route = $router->get('foo', ['middleware' => $middleware, 'uses' => function ($name) {
        return $name;
    }]);

    expect($router->gatherRouteMiddleware($route))->toEqual([
        Placeholder1::class,
        ExampleMiddleware::class,
        Authenticate::class,
        SubstituteBindings::class,
        Placeholder2::class,
        Placeholder3::class,
    ]);
});

test('model binding', function () {
    $router = getRouter();
    $router->get('foo/{bar}', ['middleware' => SubstituteBindings::class, 'uses' => function ($name) {
        return $name;
    }]);
    $router->model('bar', RouteModelBindingStub::class);
    expect($router->dispatch(Request::create('foo/taylor', 'GET'))->getContent())->toBe('TAYLOR');
});

test('model binding with null return', function () {

    $router = getRouter();
    $router->get('foo/{bar}', ['middleware' => SubstituteBindings::class, 'uses' => function ($name) {
        return $name;
    }]);
    $router->model('bar', RouteModelBindingNullStub::class);
    $router->dispatch(Request::create('foo/taylor', 'GET'))->getContent();
})->throws(ModelNotFoundException::class, 'No query results for model [Venusian\Serve\Tests\Routing\RouteModelBindingNullStub].');

test('model binding with custom null return', function () {
    $router = getRouter();
    $router->get('foo/{bar}', ['middleware' => SubstituteBindings::class, 'uses' => function ($name) {
        return $name;
    }]);
    $router->model('bar', RouteModelBindingNullStub::class, function () {
        return 'missing';
    });
    expect($router->dispatch(Request::create('foo/taylor', 'GET'))->getContent())->toBe('missing');
});

test('model binding with binding closure', function () {
    $router = getRouter();
    $router->get('foo/{bar}', ['middleware' => SubstituteBindings::class, 'uses' => function ($name) {
        return $name;
    }]);
    $router->model('bar', RouteModelBindingNullStub::class, function ($value) {
        return (new RouteModelBindingClosureStub)->findAlternate($value);
    });
    expect($router->dispatch(Request::create('foo/TAYLOR', 'GET'))->getContent())->toBe('tayloralt');
});

test('model binding with compound parameter name', function () {
    $router = getRouter();
    $router->resource('foo-bar', RouteTestResourceControllerWithModelParameter::class, ['middleware' => SubstituteBindings::class]);
    expect($router->dispatch(Request::create('foo-bar/12345', 'GET'))->getContent())->toBe('12345');
});

test('model binding with compound parameter name and route binding', function () {
    $router = getRouter();
    $router->model('foo_bar', RoutingTestUserModel::class);
    $router->resource('foo-bar', RouteTestResourceControllerWithModelParameter::class, ['middleware' => SubstituteBindings::class]);
    expect($router->dispatch(Request::create('foo-bar/12345', 'GET'))->getContent())->toBe('12345');
});

test('model binding through ioc', function () {
    $container = new ControlPanel;
    $router = new Router(new SignalDispatcher, $container);
    $container->registerInstance(Registrar::class, $router);
    $container->bind(CallableDispatcherContract::class, fn ($app) => new CallableDispatcher($app));
    $container->bind(RouteModelInterface::class, RouteModelBindingStub::class);
    $router->get('foo/{bar}', ['middleware' => SubstituteBindings::class, 'uses' => function ($name) {
        return $name;
    }]);
    $router->model('bar', RouteModelInterface::class);
    expect($router->dispatch(Request::create('foo/taylor', 'GET'))->getContent())->toBe('TAYLOR');
});

test('route dependencies can be resolved through attributes', function () {
    $container = new ControlPanel;
    $container->registerSingleton('config', fn () => new Repository([
        'app' => [
            'timezone' => 'Europe/Paris',
        ],
    ]));
    $router = new Router(new SignalDispatcher, $container);
    $container->registerInstance(Registrar::class, $router);
    $container->bind(CallableDispatcherContract::class, fn ($app) => new CallableDispatcher($app));
    $router->get('foo', [
        'middleware' => SubstituteBindings::class,
        'uses' => function (#[RouteTestConfig('app.timezone')] string $value) {
            return $value;
        },
    ]);

    expect($router->dispatch(Request::create('foo', 'GET'))->getContent())->toBe('Europe/Paris');
});

test('after resolving attribute callback is called on route dependencies resolution', function () {
    $container = new ControlPanel;
    $router = new Router(new SignalDispatcher, $container);
    $container->registerInstance(Registrar::class, $router);
    $container->bind(CallableDispatcherContract::class, fn ($app) => new CallableDispatcher($app));

    $container->afterResolvingAttribute(RoutingTestOnTenant::class, function (RoutingTestOnTenant $attribute, RoutingTestHasTenantImpl $hasTenantImpl, ControlPanel $container) {
        $hasTenantImpl->onTenant($attribute->tenant);
    });

    $router->get('foo', [
        'middleware' => SubstituteBindings::class,
        'uses' => function (#[RoutingTestOnTenant(RoutingTestTenant::TenantA)] RoutingTestHasTenantImpl $property) {
            return $property->tenant->name;
        },
    ]);

    expect($router->dispatch(Request::create('foo', 'GET'))->getContent())->toBe('TenantA');
});

test('group merging', function () {
    $old = ['prefix' => 'foo/bar/'];
    expect(RouteGroup::merge(['prefix' => 'baz'], $old))->toEqual(['prefix' => 'foo/bar/baz', 'namespace' => null, 'where' => []]);

    $old = ['domain' => 'foo'];
    expect(RouteGroup::merge(['domain' => 'baz'], $old))->toEqual(['domain' => 'baz', 'prefix' => null, 'namespace' => null, 'where' => []]);

    $old = ['as' => 'foo.'];
    expect(RouteGroup::merge(['as' => 'bar'], $old))->toEqual(['as' => 'foo.bar', 'prefix' => null, 'namespace' => null, 'where' => []]);

    $old = ['where' => ['var1' => 'foo', 'var2' => 'bar']];
    expect(RouteGroup::merge(['where' => ['var2' => 'baz', 'var3' => 'qux']], $old))->toEqual(['prefix' => null, 'namespace' => null, 'where' => [
        'var1' => 'foo', 'var2' => 'baz', 'var3' => 'qux',
    ]]);

    $old = [];
    expect(RouteGroup::merge(['where' => ['var1' => 'foo', 'var2' => 'bar']], $old))->toEqual(['prefix' => null, 'namespace' => null, 'where' => [
        'var1' => 'foo', 'var2' => 'bar',
    ]]);
});

test('route grouping', function () {
    // getPrefix() method
    $router = getRouter();
    $router->group(['prefix' => 'foo'], function () use ($router) {
        $router->get('bar', function () {
            return 'hello';
        });
    });
    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();
    expect($routes[0]->getPrefix())->toBe('foo');
});

test('route grouping outside of inherited namespace', function () {
    $router = getRouter();

    $router->group(['namespace' => 'App\Http\Controllers'], function ($router) {
        $router->group(['namespace' => '\Foo\Bar'], function ($router) {
            $router->get('users', 'UsersController@index');
        });
    });

    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();

    expect($routes[0]->getAction()['uses'])->toBe('Foo\Bar\UsersController@index');
});

test('current route uses', function () {
    $router = getRouter();
    $router->get('foo/bar', ['as' => 'foo.bar', 'uses' => RouteTestControllerStub::class.'@index']);

    expect($router->currentRouteAction())->toBeNull();

    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('Hello World');
    expect($router->uses('*RouteTestControllerStub*'))->toBeTrue();
    expect($router->uses('*RouteTestControllerStub@index'))->toBeTrue();
    expect($router->uses(['*RouteTestControllerStub*', '*FooController*']))->toBeTrue();
    expect($router->uses(['*BarController*', '*FooController*', '*RouteTestControllerStub@index']))->toBeTrue();
    expect($router->uses(['*BarController*', '*FooController*'], '*RouteTestControllerStub*'))->toBeTrue();
    expect($router->uses(['*BarController*', '*FooController*']))->toBeFalse();

    expect(RouteTestControllerStub::class.'@index')->toEqual($router->currentRouteAction());
    expect($router->currentRouteUses(RouteTestControllerStub::class.'@index'))->toBeTrue();
});

test('route grouping from file', function () {
    $router = getRouter();
    $router->group(['prefix' => 'api'], __DIR__.'/Fixtures/routes.php');

    $route = last($router->getRoutes()->get());
    $request = Request::create('api/users', 'GET');

    expect($route->matches($request))->toBeTrue();
    expect($route->bind($request)->run($request))->toBe('all-users');
});

test('route grouping with as', function () {
    $router = getRouter();
    $router->group(['prefix' => 'foo', 'as' => 'Foo::'], function () use ($router) {
        $router->get('bar', ['as' => 'bar', function () {
            return 'hello';
        }]);
    });
    $routes = $router->getRoutes();
    $route = $routes->getByName('Foo::bar');
    expect($route->uri())->toBe('foo/bar');
});

test('nested route grouping with as', function () {
    // nested with all layers present
    $router = getRouter();
    $router->group(['prefix' => 'foo', 'as' => 'Foo::'], function () use ($router) {
        $router->group(['prefix' => 'bar', 'as' => 'Bar::'], function () use ($router) {
            $router->get('baz', ['as' => 'baz', function () {
                return 'hello';
            }]);
        });
    });
    $routes = $router->getRoutes();
    $route = $routes->getByName('Foo::Bar::baz');
    expect($route->uri())->toBe('foo/bar/baz');

    // nested with layer skipped
    $router = getRouter();
    $router->group(['prefix' => 'foo', 'as' => 'Foo::'], function () use ($router) {
        $router->group(['prefix' => 'bar'], function () use ($router) {
            $router->prefix('foz')->get('baz', ['as' => 'baz', function () {
                return 'hello';
            }]);
        });
    });
    $routes = $router->getRoutes();
    $route = $routes->getByName('Foo::baz');
    expect($route->uri())->toBe('foz/foo/bar/baz');
});

test('nested route grouping prefixing', function () {
    // nested with layer skipped
    $router = getRouter();
    $router->group(['prefix' => 'foo', 'as' => 'Foo::'], function () use ($router) {
        $router->prefix('bar')->get('baz', ['as' => 'baz', function () {
            return 'hello';
        }]);
    });
    $routes = $router->getRoutes();
    $route = $routes->getByName('Foo::baz');
    expect($route->getAction('prefix'))->toBe('bar/foo');
});

test('route middleware merge with middleware attributes as strings', function () {
    $router = getRouter();
    $router->group(['prefix' => 'foo', 'middleware' => 'boo:foo'], function () use ($router) {
        $router->get('bar', function () {
            return 'hello';
        })->middleware('baz:gaz');
    });
    $routes = $router->getRoutes()->getRoutes();
    $route = $routes[0];
    expect($route->middleware())->toEqual(['boo:foo', 'baz:gaz']);
});

test('route prefixing', function () {
    // Prefix route
    $router = getRouter();
    $router->get('foo/bar', function () {
        return 'hello';
    });
    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();
    $routes[0]->prefix('prefix');
    expect($routes[0]->uri())->toBe('prefix/foo/bar');

    // Use empty prefix
    $router = getRouter();
    $router->get('foo/bar', function () {
        return 'hello';
    });
    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();
    $routes[0]->prefix('/');
    expect($routes[0]->uri())->toBe('foo/bar');

    // Prefix homepage
    $router = getRouter();
    $router->get('/', function () {
        return 'hello';
    });
    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();
    $routes[0]->prefix('prefix');
    expect($routes[0]->uri())->toBe('prefix');

    // Prefix homepage with empty prefix
    $router = getRouter();
    $router->get('/', function () {
        return 'hello';
    });
    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();
    $routes[0]->prefix('/');
    expect($routes[0]->uri())->toBe('/');
});

test('route preserving original parameters state', function () {
    $router = getRouter();
    $router->bind('bar', function ($value) {
        return strlen($value);
    });
    $router->get('foo/{bar}', [
        'middleware' => SubstituteBindings::class,
        'uses' => function ($bar) use ($router) {
            $route = $router->getCurrentRoute();

            expect($route->originalParameter('bar'))->toBe('taylor');
            expect($route->originalParameter('unexisting', 'default'))->toBe('default');
            expect($route->originalParameters())->toEqual(['bar' => 'taylor']);

            return $bar;
        },
    ]);

    expect($router->dispatch(Request::create('foo/taylor', 'GET'))->getContent())->toEqual(6);
});

test('merging controller uses', function () {
    $router = getRouter();
    $router->group(['namespace' => 'Namespace'], function () use ($router) {
        $router->get('foo/bar', 'Controller@action');
    });
    $routes = $router->getRoutes()->getRoutes();
    $action = $routes[0]->getAction();

    expect($action['controller'])->toBe('Namespace\\Controller@action');

    $router = getRouter();
    $router->group(['namespace' => 'Namespace'], function () use ($router) {
        $router->group(['namespace' => 'Nested'], function () use ($router) {
            $router->get('foo/bar', 'Controller@action');
        });
    });
    $routes = $router->getRoutes()->getRoutes();
    $action = $routes[0]->getAction();

    expect($action['controller'])->toBe('Namespace\\Nested\\Controller@action');

    $router = getRouter();
    $router->group(['prefix' => 'baz'], function () use ($router) {
        $router->group(['namespace' => 'Namespace'], function () use ($router) {
            $router->get('foo/bar', 'Controller@action');
        });
    });
    $routes = $router->getRoutes()->getRoutes();
    $action = $routes[0]->getAction();

    expect($action['controller'])->toBe('Namespace\\Controller@action');
});

test('invalid action exception', function () {

    $router = getRouter();
    $router->get('/', ['uses' => RouteTestControllerStub::class]);

    $router->dispatch(Request::create('/'));
})->throws(UnexpectedValueException::class, 'Invalid route action: [Venusian\Serve\Tests\Routing\RouteTestControllerStub].');

test('shallow resource routing', function () {
    $router = getRouter();
    $router->resource('foo.bar', 'FooController', ['shallow' => true]);
    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();

    expect($routes[0]->uri())->toBe('foo/{foo}/bar');
    expect($routes[1]->uri())->toBe('foo/{foo}/bar/create');
    expect($routes[2]->uri())->toBe('foo/{foo}/bar');

    expect($routes[3]->uri())->toBe('bar/{bar}');
    expect($routes[4]->uri())->toBe('bar/{bar}/edit');
    expect($routes[5]->uri())->toBe('bar/{bar}');
    expect($routes[6]->uri())->toBe('bar/{bar}');

    $router = getRouter();
    $router->resource('foo', 'FooController');
    $router->resource('foo.bar.baz', 'FooController', ['shallow' => true]);
    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();

    expect($routes[0]->uri())->toBe('foo');
    expect($routes[1]->uri())->toBe('foo/create');
    expect($routes[2]->uri())->toBe('foo');
    expect($routes[3]->uri())->toBe('foo/{foo}');
    expect($routes[4]->uri())->toBe('foo/{foo}/edit');
    expect($routes[5]->uri())->toBe('foo/{foo}');
    expect($routes[6]->uri())->toBe('foo/{foo}');

    expect($routes[7]->uri())->toBe('foo/{foo}/bar/{bar}/baz');
    expect($routes[8]->uri())->toBe('foo/{foo}/bar/{bar}/baz/create');
    expect($routes[9]->uri())->toBe('foo/{foo}/bar/{bar}/baz');
});

test('resource routing', function () {
    $router = getRouter();
    $router->resource('foo', 'FooController');
    $routes = $router->getRoutes();
    expect($routes)->toHaveCount(7);

    $router = getRouter();
    $router->resource('foo', 'FooController', ['only' => ['update']]);
    $routes = $router->getRoutes();

    expect($routes)->toHaveCount(1);

    $router = getRouter();
    $router->resource('foo', 'FooController', ['only' => ['show', 'destroy']]);
    $routes = $router->getRoutes();

    expect($routes)->toHaveCount(2);

    $router = getRouter();
    $router->resource('foo', 'FooController', ['except' => ['show', 'destroy']]);
    $routes = $router->getRoutes();

    expect($routes)->toHaveCount(5);

    $router = getRouter();
    $router->resource('foo-bars', 'FooController', ['only' => ['show']]);
    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();

    expect($routes[0]->uri())->toBe('foo-bars/{foo_bar}');

    $router = getRouter();
    $router->resource('foo-bar.foo-baz', 'FooController', ['only' => ['show']]);
    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();

    expect($routes[0]->uri())->toBe('foo-bar/{foo_bar}/foo-baz/{foo_baz}');

    $router = getRouter();
    $router->resource('foo-bars', 'FooController', ['only' => ['show'], 'as' => 'prefix']);
    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();

    expect($routes[0]->uri())->toBe('foo-bars/{foo_bar}');
    expect($routes[0]->getName())->toBe('prefix.foo-bars.show');

    ResourceRegistrar::verbs([
        'create' => 'ajouter',
        'edit' => 'modifier',
    ]);
    $router = getRouter();
    $router->resource('foo', 'FooController');
    $routes = $router->getRoutes();

    expect($routes->getByName('foo.create')->uri())->toBe('foo/ajouter');
    expect($routes->getByName('foo.edit')->uri())->toBe('foo/{foo}/modifier');
});

test('resource routing parameters', function () {
    ResourceRegistrar::singularParameters();

    $router = getRouter();
    $router->resource('foos', 'FooController');
    $router->resource('foos.bars', 'FooController');
    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();

    expect($routes[3]->uri())->toBe('foos/{foo}');
    expect($routes[10]->uri())->toBe('foos/{foo}/bars/{bar}');

    ResourceRegistrar::setParameters(['foos' => 'oof', 'bazs' => 'b']);

    $router = getRouter();
    $router->resource('bars.foos.bazs', 'FooController');
    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();

    expect($routes[3]->uri())->toBe('bars/{bar}/foos/{oof}/bazs/{b}');

    ResourceRegistrar::setParameters();
    ResourceRegistrar::singularParameters(false);

    $router = getRouter();
    $router->resource('foos', 'FooController', ['parameters' => 'singular']);
    $router->resource('foos.bars', 'FooController')->parameters('singular');
    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();

    expect($routes[3]->uri())->toBe('foos/{foo}');
    expect($routes[10]->uri())->toBe('foos/{foo}/bars/{bar}');

    $router = getRouter();
    $router->resource('foos.bars', 'FooController', ['parameters' => ['foos' => 'foo', 'bars' => 'bar']]);
    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();

    expect($routes[3]->uri())->toBe('foos/{foo}/bars/{bar}');

    $router = getRouter();
    $router->resource('foos.bars', 'FooController')->parameter('foos', 'foo')->parameter('bars', 'bar');
    $routes = $router->getRoutes();
    $routes = $routes->getRoutes();

    expect($routes[3]->uri())->toBe('foos/{foo}/bars/{bar}');
});

test('resource route naming', function () {
    $router = getRouter();
    $router->resource('foo', 'FooController');

    expect($router->getRoutes()->hasNamedRoute('foo.index'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.show'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.create'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.store'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.edit'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.update'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.destroy'))->toBeTrue();

    $router = getRouter();
    $router->resource('foo.bar', 'FooController');

    expect($router->getRoutes()->hasNamedRoute('foo.bar.index'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.bar.show'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.bar.create'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.bar.store'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.bar.edit'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.bar.update'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.bar.destroy'))->toBeTrue();

    $router = getRouter();
    $router->resource('prefix/foo.bar', 'FooController');

    expect($router->getRoutes()->hasNamedRoute('foo.bar.index'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.bar.show'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.bar.create'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.bar.store'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.bar.edit'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.bar.update'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('foo.bar.destroy'))->toBeTrue();

    $router = getRouter();
    $router->resource('foo', 'FooController', ['names' => [
        'index' => 'foo',
        'show' => 'bar',
    ]]);

    expect($router->getRoutes()->hasNamedRoute('foo'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('bar'))->toBeTrue();

    $router = getRouter();
    $router->resource('foo', 'FooController', ['names' => 'bar']);

    expect($router->getRoutes()->hasNamedRoute('bar.index'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('bar.show'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('bar.create'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('bar.store'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('bar.edit'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('bar.update'))->toBeTrue();
    expect($router->getRoutes()->hasNamedRoute('bar.destroy'))->toBeTrue();
});

test('router fires routed event', function () {
    $container = new ControlPanel;
    $router = new Router(new SignalDispatcher, $container);
    $container->registerInstance(Registrar::class, $router);
    $router->get('foo/bar', function () {
        return '';
    });
    $container->bind(CallableDispatcherContract::class, fn ($app) => new CallableDispatcher($app));

    $request = Request::create('http://foo.com/foo/bar', 'GET');
    $route = new Route('GET', 'foo/bar', ['http', function () {
        //
    }]);

    $_SERVER['__router.request'] = null;
    $_SERVER['__router.route'] = null;

    $router->matched(function ($event) {
        $_SERVER['__router.request'] = $event->request;
        $_SERVER['__router.route'] = $event->route;
    });

    $router->dispatchToRoute($request);

    expect($_SERVER['__router.request'])->toBeInstanceOf(Request::class);
    expect($request)->toEqual($_SERVER['__router.request']);
    unset($_SERVER['__router.request']);

    expect($_SERVER['__router.route'])->toBeInstanceOf(Route::class);
    expect($route->uri())->toEqual($_SERVER['__router.route']->uri());
    unset($_SERVER['__router.route']);
});

test('router fires route matching event', function () {
    $container = new ControlPanel;
    $router = new Router($events = new SignalDispatcher, $container);
    $container->registerInstance(Registrar::class, $router);
    $container->bind(CallableDispatcherContract::class, fn ($app) => new CallableDispatcher($app));
    $router->get('foo/bar', function () {
        return '';
    });

    $request = Request::create('http://foo.com/foo/bar', 'GET');

    $_SERVER['__router.request'] = null;

    $events->listen(Routing::class, function ($event) {
        $_SERVER['__router.request'] = $event->request;
    });

    $router->dispatchToRoute($request);

    expect($_SERVER['__router.request'])->toBeInstanceOf(Request::class);
    expect($request)->toEqual($_SERVER['__router.request']);
    unset($_SERVER['__router.request']);
});

test('router pattern setting', function () {
    $router = getRouter();
    $router->pattern('test', 'pattern');
    expect($router->getPatterns())->toEqual(['test' => 'pattern']);

    $router = getRouter();
    $router->patterns(['test' => 'pattern', 'test2' => 'pattern2']);
    expect($router->getPatterns())->toEqual(['test' => 'pattern', 'test2' => 'pattern2']);
});

test('controller routing', function () {
    unset(
        $_SERVER['route.test.controller.middleware'], $_SERVER['route.test.controller.except.middleware'],
        $_SERVER['route.test.controller.middleware.class'],
        $_SERVER['route.test.controller.middleware.parameters.one'], $_SERVER['route.test.controller.middleware.parameters.two']
    );

    $router = getRouter();

    $router->get('foo/bar', RouteTestControllerStub::class.'@index');

    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('Hello World');
    expect($_SERVER['route.test.controller.middleware'])->toBeTrue();
    expect($_SERVER['route.test.controller.middleware.class'])->toEqual(Response::class);
    expect($_SERVER['route.test.controller.middleware.parameters.one'])->toEqual(0);
    expect($_SERVER['route.test.controller.middleware.parameters.two'])->toEqual(['foo', 'bar']);
    expect(isset($_SERVER['route.test.controller.except.middleware']))->toBeFalse();
});

test('controller routing array callable', function () {
    unset(
        $_SERVER['route.test.controller.middleware'], $_SERVER['route.test.controller.except.middleware'],
        $_SERVER['route.test.controller.middleware.class'],
        $_SERVER['route.test.controller.middleware.parameters.one'], $_SERVER['route.test.controller.middleware.parameters.two']
    );

    $router = getRouter();

    $router->get('foo/bar', [RouteTestControllerStub::class, 'index']);

    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('Hello World');
    expect($_SERVER['route.test.controller.middleware'])->toBeTrue();
    expect($_SERVER['route.test.controller.middleware.class'])->toEqual(Response::class);
    expect($_SERVER['route.test.controller.middleware.parameters.one'])->toEqual(0);
    expect($_SERVER['route.test.controller.middleware.parameters.two'])->toEqual(['foo', 'bar']);
    expect(isset($_SERVER['route.test.controller.except.middleware']))->toBeFalse();
    $action = $router->getRoutes()->getRoutes()[0]->getAction()['controller'];
    expect($action)->toEqual(RouteTestControllerStub::class.'@index');
});

test('callable controller routing', function () {
    $router = getRouter();

    $router->get('foo/bar', RouteTestControllerCallableStub::class.'@bar');
    $router->get('foo/baz', RouteTestControllerCallableStub::class.'@baz');

    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('bar');
    expect($router->dispatch(Request::create('foo/baz', 'GET'))->getContent())->toBe('baz');
});

test('controller middleware groups', function () {
    unset(
        $_SERVER['route.test.controller.middleware'],
        $_SERVER['route.test.controller.middleware.class']
    );

    $router = getRouter();

    $router->middlewareGroup('web', [
        RouteTestControllerMiddleware::class,
        RouteTestControllerMiddlewareTwo::class,
    ]);

    $router->get('foo/bar', RouteTestControllerMiddlewareGroupStub::class.'@index');

    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('caught');
    expect($_SERVER['route.test.controller.middleware'])->toBeTrue();
    expect($_SERVER['route.test.controller.middleware.class'])->toEqual(Response::class);
});

test('implicit bindings', function () {
    $router = getRouter();

    $router->get('foo/{bar}', [
        'middleware' => SubstituteBindings::class,
        'uses' => function (RoutingTestUserModel $bar) {
            expect($bar)->toBeInstanceOf(RoutingTestUserModel::class);

            return $bar->value;
        },
    ]);

    expect($router->dispatch(Request::create('foo/taylor', 'GET'))->getContent())->toBe('taylor');
});

test('implicit bindings with closure', function () {
    $router = getRouter();

    $router->substituteImplicitBindingsUsing(function ($container, $route, $default) {
        $default = $default();

        $model = $route->parameter('bar');
        $model->value = 'otwell';
    });

    $router->get('foo/{bar}', [
        'middleware' => SubstituteBindings::class,
        'uses' => function (RoutingTestUserModel $bar) {
            return $bar->value;
        },
    ]);

    expect($router->dispatch(Request::create('foo/taylor', 'GET'))->getContent())->toBe('otwell');
});

test('implicit bindings where scoped bindings are prevented', function () {
    $router = getRouter();

    $router->get('foo/{test_team}/{user:id}', [
        'middleware' => SubstituteBindings::class,
        'uses' => function (RoutingTestTeamWithoutUserModel $testTeam, RoutingTestUserModel $user) {
            expect($testTeam)->toBeInstanceOf(RoutingTestTeamWithoutUserModel::class);
            expect($user)->toBeInstanceOf(RoutingTestUserModel::class);

            return $testTeam->value.'|'.$user->value;
        },
    ])->withoutScopedBindings();

    expect($router->dispatch(Request::create('foo/1/4', 'GET'))->getContent())->toBe('1|4');
});

test('parent child implicit bindings', function () {
    $router = getRouter();

    $router->get('foo/{user}/{post:slug}', [
        'middleware' => SubstituteBindings::class,
        'uses' => function (RoutingTestUserModel $user, RoutingTestPostModel $post) {
            expect($user)->toBeInstanceOf(RoutingTestUserModel::class);
            expect($post)->toBeInstanceOf(RoutingTestPostModel::class);

            return $user->value.'|'.$post->value;
        },
    ]);

    expect($router->dispatch(Request::create('foo/1/test-slug', 'GET'))->getContent())->toBe('1|test-slug');
});

test('parent child implicit bindings where only some parameters are scoped', function () {
    $router = getRouter();
    $action = function (RoutingTestTeamModel $team, RoutingTestUserModel $user, RoutingTestPostModel $post) {
        expect($team)->toBeInstanceOf(RoutingTestTeamModel::class);
        expect($user)->toBeInstanceOf(RoutingTestUserModel::class);
        expect($post)->toBeInstanceOf(RoutingTestPostModel::class);

        return $team->value.'|'.$user->value.'|'.$post->value;
    };

    $router->get('foo/{team}/{user:slug}/{post}', [
        'middleware' => SubstituteBindings::class,
        'uses' => $action,
    ]);
    expect($router->dispatch(Request::create('foo/1/test-slug/2', 'GET'))->getContent())->toBe('1|test-slug|2');

    $router->get('foo/{team}/{user}/{post:id}', [
        'middleware' => SubstituteBindings::class,
        'uses' => $action,
    ]);
    expect($router->dispatch(Request::create('foo/2/another-test-slug/3', 'GET'))->getContent())->toBe('2|another-test-slug|3');
});

test('api resource scoping when child does not belong to parent', function () {
    ResourceRegistrar::singularParameters();
    $router = getRouter();
    $router->apiResource(
        'teams.users',
        RouteTestNestedResourceControllerWithMissingUser::class,
        ['only' => ['show']],
    )
        ->middleware(SubstituteBindings::class)
        ->scoped();

    $router->dispatch(Request::create('teams/1/users/2', 'GET'));
})->throws(ModelNotFoundException::class);

test('parent child implicit bindings properly camel cased', function () {
    $router = getRouter();

    $router->get('foo/{user}/{test_team:id}', [
        'middleware' => SubstituteBindings::class,
        'uses' => function (RoutingTestUserModel $user, RoutingTestTeamModel $testTeam) {
            expect($user)->toBeInstanceOf(RoutingTestUserModel::class);
            expect($testTeam)->toBeInstanceOf(RoutingTestTeamModel::class);

            return $user->value.'|'.$testTeam->value;
        },
    ]);

    expect($router->dispatch(Request::create('foo/1/4', 'GET'))->getContent())->toBe('1|4');
});

test('implicit bindings with optional parameter with existing key in uri', function () {
    $router = getRouter();
    $router->get('foo/{bar?}', [
        'middleware' => SubstituteBindings::class,
        'uses' => function (?RoutingTestUserModel $bar = null) {
            expect($bar)->toBeInstanceOf(RoutingTestUserModel::class);

            return $bar->value;
        },
    ]);
    expect($router->dispatch(Request::create('foo/taylor', 'GET'))->getContent())->toBe('taylor');
});

test('optional backed enums return null when missing', function () {
    $router = getRouter();
    $router->get('foo/{bar?}', [
        'middleware' => SubstituteBindings::class,
        'uses' => function (?CategoryBackedEnum $bar = null) {
            expect($bar)->toBeNull();

            return 'bar';
        },
    ]);

    $router->dispatch(Request::create('foo', 'GET'))->getContent();
});

test('implicit bindings with missing model handled by missing', function () {
    $router = getRouter();
    $router->get('foo/{bar}', [
        'middleware' => SubstituteBindings::class,
        'uses' => function (?RouteModelBindingNullStub $bar = null) {
            expect($bar)->toBeInstanceOf(RouteModelBindingNullStub::class);

            return $bar->first();
        },
    ])->missing(function () {
        return new RedirectResponse('/', 302);
    });

    $request = Request::create('foo/taylor', 'GET');

    $response = $router->dispatch($request);
    expect($response->isRedirect('/'))->toBeTrue();
    expect($response->getStatusCode())->toEqual(302);
});

test('implicit bindings with missing model handled by missing on group level', function () {
    $router = getRouter();
    $router->as('foo.')
        ->missing(fn () => new RedirectResponse('/', 302))
        ->group(function () use ($router) {
            $router->get('foo/{bar}', [
                'middleware' => SubstituteBindings::class,
                'uses' => function (?RouteModelBindingNullStub $bar = null) {
                    expect($bar)->toBeInstanceOf(RouteModelBindingNullStub::class);

                    return $bar->first();
                },
            ]);
        });

    $request = Request::create('foo/taylor', 'GET');

    $response = $router->dispatch($request);
    expect($response->isRedirect('/'))->toBeTrue();
    expect($response->getStatusCode())->toEqual(302);
});

test('implicit bindings with optional parameter with no key in uri', function () {
    $router = getRouter();
    $router->get('foo/{bar?}', [
        'middleware' => SubstituteBindings::class,
        'uses' => function (?RoutingTestUserModel $bar = null) {
            expect($bar)->toBeNull();
        },
    ]);
    $router->dispatch(Request::create('foo', 'GET'))->getContent();
});

test('implicit bindings with optional parameter using enum is always casted to enum', function () {
    include_once 'Fixtures/Enums.php';

    $router = getRouter();
    $router->get('foo/{bar?}', [
        'middleware' => SubstituteBindings::class,
        'uses' => function (?\Venusian\Serve\Tests\Routing\Fixtures\CategoryBackedEnum $bar = null) {
            expect($bar)->toBeInstanceOf(CategoryBackedEnum::class);
        },
    ]);
    $router->dispatch(Request::create('foo/people', 'GET'))->getContent();
});

test('implicit bindings with optional parameter with non existing key in uri', function () {

    $router = getRouter();
    $router->get('foo/{bar?}', [
        'middleware' => SubstituteBindings::class,
        'uses' => function (?RoutingTestNonExistingUserModel $bar = null) {
            $this->fail('ModelNotFoundException was expected.');
        },
    ]);
    $router->dispatch(Request::create('foo/nonexisting', 'GET'))->getContent();
})->throws(ModelNotFoundException::class);

test('implicit binding through ioc', function () {
    $container = new ControlPanel;
    $router = new Router(new SignalDispatcher, $container);
    $container->registerInstance(Registrar::class, $router);
    $container->bind(CallableDispatcherContract::class, fn ($app) => new CallableDispatcher($app));

    $container->bind(RoutingTestUserModel::class, RoutingTestExtendedUserModel::class);
    $router->get('foo/{bar}', [
        'middleware' => SubstituteBindings::class,
        'uses' => function (RoutingTestUserModel $bar) {
            expect($bar)->toBeInstanceOf(RoutingTestExtendedUserModel::class);
        },
    ]);
    $router->dispatch(Request::create('foo/baz', 'GET'))->getContent();
});

test('dispatching callable action classes', function () {
    $router = getRouter();
    $router->get('foo/bar', ActionStub::class);

    expect($router->dispatch(Request::create('foo/bar', 'GET'))->getContent())->toBe('hello');

    $router->get('foo/bar2', [
        'uses' => ActionStub::class,
    ]);

    expect($router->dispatch(Request::create('foo/bar2', 'GET'))->getContent())->toBe('hello');
});

test('response is returned', function () {
    $router = getRouter();
    $router->get('foo/bar', function () {
        return 'hello';
    });

    $response = $router->dispatch(Request::create('foo/bar', 'GET'));
    expect($response)->toBeInstanceOf(Response::class);
    expect($response)->not->toBeInstanceOf(JsonResponse::class);
});

test('redirect response can be returned as responsable', function () {
    $router = getRouter();
    $router->get('foo/bar', function (): Responsable {
        return new RedirectResponse('/destination', 303, ['X-Test' => 'value']);
    });

    $response = $router->dispatch(Request::create('foo/bar', 'GET'));

    expect($response)->toBeInstanceOf(RedirectResponse::class);
    expect($response->getStatusCode())->toBe(303);
    expect($response->headers->get('Location'))->toBe('/destination');
    expect($response->headers->get('X-Test'))->toBe('value');
});

test('json response is returned', function () {
    $router = getRouter();
    $router->get('foo/bar', function () {
        return ['foo', 'bar'];
    });

    $response = $router->dispatch(Request::create('foo/bar', 'GET'));
    expect($response)->not->toBeInstanceOf(Response::class);
    expect($response)->toBeInstanceOf(JsonResponse::class);
});

test('route redirect', function () {
    $container = new ControlPanel;
    $router = new Router(new SignalDispatcher, $container);
    $container->registerInstance(Registrar::class, $router);
    $request = Request::create('contact_us', 'GET');
    $container->registerInstance(Request::class, $request);
    $urlGenerator = new UrlGenerator(new RouteCollection, $request);
    $container->registerInstance(UrlGenerator::class, $urlGenerator);
    $router->get('contact_us', function () {
        throw new Exception('Route should not be reachable.');
    });
    $router->redirect('contact_us', 'contact');

    $response = $router->dispatch($request);
    expect($response->isRedirect('contact'))->toBeTrue();
    expect($response->getStatusCode())->toEqual(302);
});

test('route redirect retains existing starting forward slash', function () {
    $container = new ControlPanel;
    $router = new Router(new SignalDispatcher, $container);
    $container->registerInstance(Registrar::class, $router);
    $request = Request::create('contact_us', 'GET');
    $container->registerInstance(Request::class, $request);
    $urlGenerator = new UrlGenerator(new RouteCollection, $request);
    $container->registerInstance(UrlGenerator::class, $urlGenerator);
    $router->get('contact_us', function () {
        throw new Exception('Route should not be reachable.');
    });
    $router->redirect('contact_us', '/contact');

    $response = $router->dispatch($request);
    expect($response->isRedirect('/contact'))->toBeTrue();
    expect($response->getStatusCode())->toEqual(302);
});

test('route redirect strips missing starting forward slash', function () {
    $container = new ControlPanel;
    $router = new Router(new SignalDispatcher, $container);
    $container->registerInstance(Registrar::class, $router);
    $request = Request::create('contact_us', 'GET');
    $container->registerInstance(Request::class, $request);
    $urlGenerator = new UrlGenerator(new RouteCollection, $request);
    $container->registerInstance(UrlGenerator::class, $urlGenerator);
    $router->get('contact_us', function () {
        throw new Exception('Route should not be reachable.');
    });
    $router->redirect('contact_us', 'contact');

    $response = $router->dispatch($request);
    expect($response->isRedirect('contact'))->toBeTrue();
    expect($response->getStatusCode())->toEqual(302);
});

test('route redirect exception when missing expected parameters', function () {

    $container = new ControlPanel;
    $router = new Router(new SignalDispatcher, $container);
    $container->registerInstance(Registrar::class, $router);
    $request = Request::create('users', 'GET');
    $container->registerInstance(Request::class, $request);
    $urlGenerator = new UrlGenerator(new RouteCollection, $request);
    $container->registerInstance(UrlGenerator::class, $urlGenerator);
    $router->get('users', function () {
        throw new Exception('Route should not be reachable.');
    });
    $router->redirect('users', 'users/{user}');

    $router->dispatch($request);
})->throws(UrlGenerationException::class, 'Missing required parameter for [Route: mariner_route_redirect_destination] [URI: users/{user}] [Missing parameter: user].');

test('route redirect with custom status', function () {
    $container = new ControlPanel;
    $router = new Router(new SignalDispatcher, $container);
    $container->registerInstance(Registrar::class, $router);
    $request = Request::create('contact_us', 'GET');
    $container->registerInstance(Request::class, $request);
    $urlGenerator = new UrlGenerator(new RouteCollection, $request);
    $container->registerInstance(UrlGenerator::class, $urlGenerator);
    $router->get('contact_us', function () {
        throw new Exception('Route should not be reachable.');
    });
    $router->redirect('contact_us', 'contact', 301);

    $response = $router->dispatch($request);
    expect($response->isRedirect('contact'))->toBeTrue();
    expect($response->getStatusCode())->toEqual(301);
});

test('route permanent redirect', function () {
    $container = new ControlPanel;
    $router = new Router(new SignalDispatcher, $container);
    $container->registerInstance(Registrar::class, $router);
    $request = Request::create('contact_us', 'GET');
    $container->registerInstance(Request::class, $request);
    $urlGenerator = new UrlGenerator(new RouteCollection, $request);
    $container->registerInstance(UrlGenerator::class, $urlGenerator);
    $router->get('contact_us', function () {
        throw new Exception('Route should not be reachable.');
    });
    $router->permanentRedirect('contact_us', 'contact');

    $response = $router->dispatch($request);
    expect($response->isRedirect('contact'))->toBeTrue();
    expect($response->getStatusCode())->toEqual(301);
});

test('route can middleware can be assigned', function () {
    $route = new Route(['GET'], '/', []);
    $route->middleware(['foo'])->can('create', Route::class);

    expect($route->middleware())->toEqual([
        'foo',
        'can:create,Mariner\Routing\Route',
    ]);

    $route = new Route(['GET'], '/', []);
    $route->can('create');

    expect($route->middleware())->toEqual([
        'can:create',
    ]);

    $route = new Route(['GET'], '/', []);
    $route->can('create');
    $route->can('update');

    expect($route->middleware())->toEqual([
        'can:create',
        'can:update',
    ]);
});

test('it dispatches events while preparing request', function () {
    $events = new SignalDispatcher;
    $preparing = [];
    $prepared = [];
    $events->listen(PreparingResponse::class, function ($event) use (&$preparing) {
        $preparing[] = $event;
    });
    $events->listen(ResponsePrepared::class, function ($event) use (&$prepared) {
        $prepared[] = $event;
    });
    $container = new ControlPanel;
    $container->registerInstance(SignalDispatcher::class, $events);
    $router = getRouter($container);
    $router->get('foo/bar', function () {
        return 'hello';
    });
    $request = Request::create('foo/bar', 'GET');

    $response = $router->dispatch($request);

    expect($response->getContent())->toBe('hello');
    expect($preparing)->toHaveCount(2);
    expect($preparing[0]->request)->toBe($request);
    expect($preparing[0]->response)->toBe('hello');
    expect($preparing[1]->request)->toBe($request);
    expect($preparing[1]->response)->toBe($response);
    expect($prepared)->toHaveCount(2);
    expect($prepared[0]->request)->toBe($request);
    expect($prepared[0]->response)->toBe($response);
    expect($prepared[1]->request)->toBe($request);
    expect($prepared[1]->response)->toBe($response);
});

function getRouter($container = null)
{
    $container ??= new ControlPanel;

    $router = new Router($container->make(SignalDispatcher::class), $container);

    $container->registerInstance(Registrar::class, $router);

    $container->bind(ControllerDispatcherContract::class, fn ($app) => new ControllerDispatcher($app));
    $container->bind(CallableDispatcherContract::class, fn ($app) => new CallableDispatcher($app));

    return $router;
}

test('route deserialization allowed classes', function () {
    $badObject = new RouteTestInsecureDeserializationStub;
    $closureWithUse = function () use ($badObject) {
        return $badObject;
    };

    $serializedClosure = serialize(\Laravel\SerializableClosure\SerializableClosure::unsigned($closureWithUse));

    RouteTestInsecureDeserializationStub::$instantiated = false;
    unserialize($serializedClosure);
    expect(RouteTestInsecureDeserializationStub::$instantiated)->toBeTrue();
    $route = new Route(['GET'], 'foo', [
        'uses' => $serializedClosure,
    ]);

    RouteTestInsecureDeserializationStub::$instantiated = false;

    try {
        $route->run();
    } catch (\Throwable) {
        //
    }

    expect(RouteTestInsecureDeserializationStub::$instantiated)->toBeFalse();
});

class RouteTestControllerStub extends Controller
{
    public function __construct()
    {
        $this->middleware(RouteTestControllerMiddleware::class);
        $this->middleware(RouteTestControllerParameterizedMiddlewareOne::class.':0');
        $this->middleware(RouteTestControllerParameterizedMiddlewareTwo::class.':foo,bar');
        $this->middleware(RouteTestControllerExceptMiddleware::class, ['except' => 'index']);
    }

    public function index()
    {
        return 'Hello World';
    }
}

class RouteTestControllerCallableStub extends Controller
{
    public function __call($method, $arguments = [])
    {
        return $method;
    }
}

class RouteTestControllerMiddlewareGroupStub extends Controller
{
    public function __construct()
    {
        $this->middleware('web');
    }

    public function index()
    {
        return 'Hello World';
    }
}

class RouteTestControllerWithParameterStub extends Controller
{
    public function returnParameter($bar = '')
    {
        return $bar;
    }
}

class RouteTestAnotherControllerWithParameterStub extends Controller
{
    public function callAction($method, $parameters)
    {
        $_SERVER['__test.controller_callAction_parameters'] = $parameters;
    }

    public function oneArgument($one)
    {
        //
    }

    public function twoArguments($one, $two)
    {
        //
    }

    public function differentArgumentNames($bar, $baz)
    {
        //
    }

    public function reversedArguments($two, $one)
    {
        //
    }

    public function withModels(Request $request, RoutingTestUserModel $user, $defaultNull = null, ?RoutingTestTeamModel $team = null)
    {
        //
    }
}

class RouteTestResourceControllerWithModelParameter extends Controller
{
    public function show(RoutingTestUserModel $fooBar)
    {
        return $fooBar->value;
    }
}

class RouteTestNestedResourceControllerWithMissingUser extends Controller
{
    public function show(RoutingTestTeamWithoutUserModel $team, RoutingTestUserModel $user)
    {
    }
}

class RouteTestClosureMiddlewareController extends Controller
{
    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $response = $next($request);

            return $response->setContent(
                $response->content().'-'.$request['foo-middleware'].'-controller-closure'
            );
        });
    }

    public function index()
    {
        return 'index';
    }
}

class RouteTestControllerMiddleware
{
    public function handle($request, $next)
    {
        $_SERVER['route.test.controller.middleware'] = true;
        $response = $next($request);
        $_SERVER['route.test.controller.middleware.class'] = get_class($response);

        return $response;
    }
}

class RouteTestControllerMiddlewareTwo
{
    public function handle($request, $next)
    {
        return new Response('caught');
    }
}

class RouteTestControllerParameterizedMiddlewareOne
{
    public function handle($request, $next, $parameter)
    {
        $_SERVER['route.test.controller.middleware.parameters.one'] = $parameter;

        return $next($request);
    }
}

class RouteTestControllerParameterizedMiddlewareTwo
{
    public function handle($request, $next, $parameter1, $parameter2)
    {
        $_SERVER['route.test.controller.middleware.parameters.two'] = [$parameter1, $parameter2];

        return $next($request);
    }
}

class RouteTestControllerExceptMiddleware
{
    public function handle($request, $next)
    {
        $_SERVER['route.test.controller.except.middleware'] = true;

        return $next($request);
    }
}

class ResponsableResponse implements Responsable
{
    public function toResponse($request)
    {
        return new Response('bar');
    }
}

class RouteBindingStub
{
    public function bind($value, $route)
    {
        return strtoupper($value);
    }

    public function find($value, $route)
    {
        return strtolower($value);
    }
}

#[\Attribute(\Attribute::TARGET_PARAMETER)]
class RouteTestConfig implements \Voyager\Contracts\Vessel\ContextualAttribute
{
    public function __construct(public string $key)
    {
    }

    public static function resolve(self $attribute, ControlPanel $container)
    {
        return $container->make('config')->get($attribute->key);
    }
}

class RouteModelBindingStub extends Model implements UrlRoutable
{
    use BindsToRoutes;

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function where($key, $value)
    {
        $this->value = $value;

        return $this;
    }

    public function first()
    {
        return strtoupper($this->value);
    }
}

class RouteModelBindingNullStub extends Model implements UrlRoutable
{
    use BindsToRoutes;

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function where($key, $value)
    {
        return $this;
    }

    public function first()
    {
        //
    }
}

class RouteModelBindingClosureStub
{
    public function findAlternate($value)
    {
        return strtolower($value).'alt';
    }
}

class RoutingTestMiddlewareGroupOne
{
    public function handle($request, $next)
    {
        $_SERVER['__middleware.group'] = true;

        return $next($request);
    }
}

class RoutingTestMiddlewareGroupTwo
{
    public function handle($request, $next, $parameter = null)
    {
        return new Response('caught '.$parameter);
    }
}

class RoutingTestUserModel extends Model implements UrlRoutable
{
    use BindsToRoutes;

    public function posts()
    {
        return new RoutingTestPostModel;
    }

    public function testTeams()
    {
        return new RoutingTestTeamModel;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function where($key, $value)
    {
        $this->value = $value;

        return $this;
    }

    public function first()
    {
        return $this;
    }

    public function firstOrFail()
    {
        return $this;
    }
}

class RoutingTestPostModel extends Model implements UrlRoutable
{
    use BindsToRoutes;

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function where($key, $value)
    {
        $this->value = $value;

        return $this;
    }

    public function first()
    {
        return $this;
    }
}

class RoutingTestTeamModel extends Model implements UrlRoutable
{
    use BindsToRoutes;

    public function users()
    {
        return new RoutingTestUserModel;
    }

    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function where($key, $value)
    {
        $this->value = $value;

        return $this;
    }

    public function first()
    {
        return $this;
    }

    public function firstOrFail()
    {
        return $this;
    }
}

class RoutingTestExtendedUserModel extends RoutingTestUserModel
{
    //
}

class RoutingTestNonExistingUserModel extends RoutingTestUserModel
{
    public function first()
    {
        //
    }

    public function firstOrFail()
    {
        throw new ModelNotFoundException;
    }
}

class RoutingTestTeamWithoutUserModel extends RoutingTestTeamModel
{
    public function users()
    {
        throw new ModelNotFoundException();
    }
}

class ActionStub
{
    public function __invoke()
    {
        return 'hello';
    }
}

class ActionCountStub extends Controller
{
    protected $middlewareInvokedCount = 0;

    protected $invokedCount = 0;

    public function __construct()
    {
        $this->middleware(function ($request, $next) {
            $this->middlewareInvokedCount++;

            return $next($request);
        });
    }

    public function __invoke()
    {
        $this->invokedCount++;

        return [
            'invokedCount' => $this->invokedCount,
            'middlewareInvokedCount' => $this->middlewareInvokedCount,
        ];
    }
}

interface ExampleMiddlewareContract
{
    //
}

class ExampleMiddleware implements ExampleMiddlewareContract
{
    public function handle($request, Closure $next)
    {
        return $next($request);
    }
}

#[Attribute(Attribute::TARGET_PARAMETER)]
final readonly class RoutingTestOnTenant
{
    public function __construct(
        public RoutingTestTenant $tenant
    ) {
    }
}

enum RoutingTestTenant
{
    case TenantA;
    case TenantB;
}

final class RoutingTestHasTenantImpl
{
    public ?RoutingTestTenant $tenant = null;

    public function onTenant(RoutingTestTenant $tenant): void
    {
        $this->tenant = $tenant;
    }
}

class RouteTestInsecureDeserializationStub
{
    public static $instantiated = false;

    public function __wakeup()
    {
        self::$instantiated = true;
    }
}

test('a router built without a scoped route state still remembers the current route', function () {
    $router = getRouter();
    $router->get('foo', ['as' => 'foo', fn () => 'bar']);

    $request = Request::create('foo', 'GET');
    $router->dispatch($request);

    expect($router->currentRouteName())->toBe('foo')
        ->and($router->getCurrentRequest())->toBe($request);
});

test('the app router keeps its state in the scoped route state, never its local fallback', function () {
    $app = WebApp::boot();
    $router = $app['router'];
    $router->get('foo', ['as' => 'foo', fn () => 'bar']);

    $request = Request::create('foo', 'GET');
    $app->registerInstance('request', $request);
    $router->dispatch($request);

    expect($app->make(RouteState::class)->current?->getName())->toBe('foo')
        ->and((new \ReflectionProperty($router, 'localRouteState'))->getValue($router))->toBeNull();

    WebApp::tearDown($this);
});
