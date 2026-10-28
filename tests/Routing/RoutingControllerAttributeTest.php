<?php

namespace Venusian\Serve\Tests\Routing;

use Voyager\Vessel\ControlPanel;
use Mariner\Routing\Attributes\Controllers\Middleware;
use Mariner\Routing\Controller as RoutingController;
use Mariner\Routing\Controllers\HasMiddleware;
use Mariner\Routing\Route;
use Override;

test('controller middleware attributes are inherited', function () {
    $route = new Route('GET', 'foo', ['uses' => InheritMiddlewareController::class.'@index']);
    $route->setContainer(new ControlPanel);

    expect($route->gatherMiddleware())->toEqual(['auth', 'log']);
});

test('controller middleware attributes are inherited in declaration order', function () {
    $route = new Route('GET', 'foo', ['uses' => InheritMiddlewareDeclarationOrderController::class.'@index']);
    $route->setContainer(new ControlPanel);

    expect($route->gatherMiddleware())->toEqual(['middleware1', 'middleware2', 'middleware3']);
});

test('controller middleware merges with attribute middleware', function () {
    $route = new Route('GET', 'foo', ['uses' => StaticMiddlewareController::class.'@index']);
    $route->setContainer(new ControlPanel);

    expect($route->gatherMiddleware())->toEqual(['static-middleware', 'attribute-middleware-1', 'attribute-middleware-2']);

    $route = new Route('GET', 'bar', ['uses' => DynamicMiddlewareController::class.'@index']);
    $route->setContainer(new ControlPanel);

    expect($route->gatherMiddleware())->toEqual(['dynamic-middleware', 'attribute-middleware-1', 'attribute-middleware-2']);
});

abstract class Controller
{
    //
}

#[Middleware('auth')]
abstract class BaseMiddlewareController extends Controller
{
    //
}

#[Middleware('log')]
class InheritMiddlewareController extends BaseMiddlewareController
{
    public function index()
    {
        //
    }
}

#[Middleware('middleware1')]
#[Middleware('middleware2')]
abstract class BaseMiddlewareDeclarationOrderController extends Controller
{
    //
}

#[Middleware('middleware3')]
class InheritMiddlewareDeclarationOrderController extends BaseMiddlewareDeclarationOrderController
{
    public function index()
    {
        //
    }
}

#[Middleware('attribute-middleware-1')]
class StaticMiddlewareController implements HasMiddleware
{
    #[Override]
    public static function middleware(): array
    {
        return ['static-middleware'];
    }

    #[Middleware('attribute-middleware-2')]
    public function index()
    {
        //
    }
}

#[Middleware('attribute-middleware-1')]
class DynamicMiddlewareController extends RoutingController
{
    public function __construct()
    {
        $this->middleware('dynamic-middleware');
    }

    #[Middleware('attribute-middleware-2')]
    public function index()
    {
        //
    }
}
