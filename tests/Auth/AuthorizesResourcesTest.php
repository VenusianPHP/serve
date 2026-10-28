<?php

namespace Venusian\Serve\Tests\Auth;

use Closure;
use Voyager\Signals\SignalDispatcher;
use Mariner\Auth\Access\AuthorizesRequests;
use Mariner\Http\Request;
use Mariner\Routing\Controller;
use Mariner\Routing\Router;

test('create method', function () {
    $controller = new AuthorizesResourcesController;

    assertHasMiddleware($controller, 'create', 'can:create,App\User');

    $controller = new AuthorizesResourcesWithArrayController;

    assertHasMiddleware($controller, 'create', 'can:create,App\User,App\Post');
});

test('store method', function () {
    $controller = new AuthorizesResourcesController;

    assertHasMiddleware($controller, 'store', 'can:create,App\User');

    $controller = new AuthorizesResourcesWithArrayController;

    assertHasMiddleware($controller, 'store', 'can:create,App\User,App\Post');
});

test('show method', function () {
    $controller = new AuthorizesResourcesController;

    assertHasMiddleware($controller, 'show', 'can:view,user');

    $controller = new AuthorizesResourcesWithArrayController;

    assertHasMiddleware($controller, 'show', 'can:view,user,post');
});

test('edit method', function () {
    $controller = new AuthorizesResourcesController;

    assertHasMiddleware($controller, 'edit', 'can:update,user');

    $controller = new AuthorizesResourcesWithArrayController;

    assertHasMiddleware($controller, 'edit', 'can:update,user,post');
});

test('update method', function () {
    $controller = new AuthorizesResourcesController;

    assertHasMiddleware($controller, 'update', 'can:update,user');

    $controller = new AuthorizesResourcesWithArrayController;

    assertHasMiddleware($controller, 'update', 'can:update,user,post');
});

test('destroy method', function () {
    $controller = new AuthorizesResourcesController;

    assertHasMiddleware($controller, 'destroy', 'can:delete,user');

    $controller = new AuthorizesResourcesWithArrayController;

    assertHasMiddleware($controller, 'destroy', 'can:delete,user,post');
});

function assertHasMiddleware($controller, $method, $middleware)
{
    $router = new Router(new SignalDispatcher);

    $router->aliasMiddleware('can', AuthorizesResourcesMiddleware::class);
    $router->get($method)->uses(get_class($controller).'@'.$method);

    expect($router->dispatch(Request::create($method, 'GET'))->getContent())->toBe('caught '.$middleware);
}

class AuthorizesResourcesController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource('App\User', 'user');
    }

    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function store()
    {
        //
    }

    public function show()
    {
        //
    }

    public function edit()
    {
        //
    }

    public function update()
    {
        //
    }

    public function destroy()
    {
        //
    }
}

class AuthorizesResourcesWithArrayController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        $this->authorizeResource(['App\User', 'App\Post'], ['user', 'post']);
    }

    public function index()
    {
        //
    }

    public function create()
    {
        //
    }

    public function store()
    {
        //
    }

    public function show()
    {
        //
    }

    public function edit()
    {
        //
    }

    public function update()
    {
        //
    }

    public function destroy()
    {
        //
    }
}

class AuthorizesResourcesMiddleware
{
    public function handle($request, Closure $next, $method, $parameter, ...$models)
    {
        $params = array_merge([$parameter], $models);

        return "caught can:{$method},".implode(',', $params);
    }
}
