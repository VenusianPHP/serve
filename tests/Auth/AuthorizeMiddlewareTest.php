<?php

namespace Venusian\Serve\Tests\Auth;

use Mariner\Auth\Access\AuthorizationException;
use Mariner\Auth\Access\Gate;
use Mariner\Auth\Middleware\Authorize;
use Voyager\Vessel\ControlPanel;
use Mariner\Contracts\Auth\Access\Gate as GateContract;
use Mariner\Contracts\Routing\Registrar;
use Voyager\Database\Instrument\Model;
use Voyager\Signals\SignalDispatcher;
use Mariner\Http\Request;
use Mariner\Routing\CallableDispatcher;
use Mariner\Routing\Contracts\CallableDispatcher as CallableDispatcherContract;
use Mariner\Routing\Middleware\SubstituteBindings;
use Mariner\Routing\Router;
use Venusian\Serve\Tests\Auth\Fixtures\AbilitiesEnum;
use stdClass;

require_once __DIR__.'/Fixtures/Enums.php';

beforeEach(function () {
    $this->user = new stdClass;

    ControlPanel::setInstance($this->container = new ControlPanel);

    $this->container->registerSingleton(GateContract::class, function () {
        return new Gate($this->container, function () {
            return $this->user;
        });
    });

    $this->router = new Router(new SignalDispatcher($this->container), $this->container);

    $this->container->bind(CallableDispatcherContract::class, fn ($app) => new CallableDispatcher($app));

    $this->container->registerInstance(Registrar::class, $this->router);
});

afterEach(function () {
    ControlPanel::setInstance(null);
});

test('it can generate definition via static method', function () {
    $signature = Authorize::using('ability');
    expect($signature)->toBe('Mariner\Auth\Middleware\Authorize:ability');

    $signature = Authorize::using('ability', 'model');
    expect($signature)->toBe('Mariner\Auth\Middleware\Authorize:ability,model');

    $signature = Authorize::using('ability', 'model', \App\Models\Comment::class);
    expect($signature)->toBe('Mariner\Auth\Middleware\Authorize:ability,model,App\Models\Comment');
});

test('simple ability unauthorized', function () {
    $this->container->make(GateContract::class)->define('view-dashboard', function ($user, $additional = null) {
        expect($additional)->toBeNull();

        return false;
    });

    $this->router->get('dashboard', [
        'middleware' => Authorize::class.':view-dashboard',
        'uses' => function () {
            return 'success';
        },
    ]);

    $this->router->dispatch(Request::create('dashboard', 'GET'));
})->throws(AuthorizationException::class, 'This action is unauthorized.');

test('simple ability authorized', function () {
    $this->container->make(GateContract::class)->define('view-dashboard', function ($user) {
        return true;
    });

    $this->router->get('dashboard', [
        'middleware' => Authorize::class.':view-dashboard',
        'uses' => function () {
            return 'success';
        },
    ]);

    $response = $this->router->dispatch(Request::create('dashboard', 'GET'));

    expect($response->content())->toBe('success');
});

test('simple ability with string parameter', function () {
    $this->container->make(GateContract::class)->define('view-dashboard', function ($user, $param) {
        return $param === 'some string';
    });

    $this->router->get('dashboard', [
        'middleware' => Authorize::class.':view-dashboard,"some string"',
        'uses' => function () {
            return 'success';
        },
    ]);

    $response = $this->router->dispatch(Request::create('dashboard', 'GET'));

    expect($response->content())->toBe('success');
});

test('simple ability with backed enum parameter', function () {
    $this->container->make(GateContract::class)->define('view-dashboard', function ($user) {
        return true;
    });

    $this->router->middleware(Authorize::using(AbilitiesEnum::VIEW_DASHBOARD))->get('dashboard', [
        'uses' => function () {
            return 'success';
        },
    ]);

    $response = $this->router->dispatch(Request::create('dashboard', 'GET'));

    expect($response->content())->toBe('success');
});

test('simple ability with null parameter', function () {
    $this->container->make(GateContract::class)->define('view-dashboard', function ($user, $param = null) {
        expect($param)->toBeNull();

        return true;
    });

    $this->router->get('dashboard', [
        'middleware' => Authorize::class.':view-dashboard,null',
        'uses' => function () {
            return 'success';
        },
    ]);

    $this->router->dispatch(Request::create('dashboard', 'GET'));
});

test('simple ability with optional parameter', function () {
    $post = new stdClass;

    $this->router->bind('post', function () use ($post) {
        return $post;
    });

    $this->container->make(GateContract::class)->define('view-comments', function ($user, $model = null) {
        return true;
    });

    $middleware = [SubstituteBindings::class, Authorize::class.':view-comments,post'];

    $this->router->get('comments', [
        'middleware' => $middleware,
        'uses' => function () {
            return 'success';
        },
    ]);
    $this->router->get('posts/{post}/comments', [
        'middleware' => $middleware,
        'uses' => function () {
            return 'success';
        },
    ]);

    $response = $this->router->dispatch(Request::create('posts/1/comments', 'GET'));
    expect($response->content())->toBe('success');

    $response = $this->router->dispatch(Request::create('comments', 'GET'));
    expect($response->content())->toBe('success');
});

test('simple ability with string parameter from route parameter', function () {
    $this->container->make(GateContract::class)->define('view-dashboard', function ($user, $param) {
        return $param === 'true';
    });

    $this->router->get('dashboard/{route_parameter}', [
        'middleware' => Authorize::class.':view-dashboard,route_parameter',
        'uses' => function () {
            return 'success';
        },
    ]);

    $response = $this->router->dispatch(Request::create('dashboard/true', 'GET'));

    expect($response->content())->toBe('success');
});

test('simple ability with string parameter0 from route parameter', function () {
    $this->container->make(GateContract::class)->define('view-dashboard', function ($user, $param) {
        return $param === '0';
    });

    $this->router->get('dashboard/{route_parameter}', [
        'middleware' => Authorize::class.':view-dashboard,route_parameter',
        'uses' => function () {
            return 'success';
        },
    ]);

    $response = $this->router->dispatch(Request::create('dashboard/0', 'GET'));

    expect($response->content())->toBe('success');
});

test('model type unauthorized', function () {

    $this->container->make(GateContract::class)->define('create', function ($user, $model) {
        expect($model)->toBe('App\User');

        return false;
    });

    $this->router->get('users/create', [
        'middleware' => [SubstituteBindings::class, Authorize::class.':create,App\User'],
        'uses' => function () {
            return 'success';
        },
    ]);

    $this->router->dispatch(Request::create('users/create', 'GET'));
})->throws(AuthorizationException::class, 'This action is unauthorized.');

test('model type authorized', function () {
    $this->container->make(GateContract::class)->define('create', function ($user, $model) {
        expect($model)->toBe('App\User');

        return true;
    });

    $this->router->get('users/create', [
        'middleware' => Authorize::class.':create,App\User',
        'uses' => function () {
            return 'success';
        },
    ]);

    $response = $this->router->dispatch(Request::create('users/create', 'GET'));

    expect($response->content())->toBe('success');
});

test('model unauthorized', function () {

    $post = new stdClass;

    $this->router->bind('post', function () use ($post) {
        return $post;
    });

    $this->container->make(GateContract::class)->define('edit', function ($user, $model) use ($post) {
        expect($post)->toBe($model);

        return false;
    });

    $this->router->get('posts/{post}/edit', [
        'middleware' => [SubstituteBindings::class, Authorize::class.':edit,post'],
        'uses' => function () {
            return 'success';
        },
    ]);

    $this->router->dispatch(Request::create('posts/1/edit', 'GET'));
})->throws(AuthorizationException::class, 'This action is unauthorized.');

test('model authorized', function () {
    $post = new stdClass;

    $this->router->bind('post', function () use ($post) {
        return $post;
    });

    $this->container->make(GateContract::class)->define('edit', function ($user, $model) use ($post) {
        expect($post)->toBe($model);

        return true;
    });

    $this->router->get('posts/{post}/edit', [
        'middleware' => [SubstituteBindings::class, Authorize::class.':edit,post'],
        'uses' => function () {
            return 'success';
        },
    ]);

    $response = $this->router->dispatch(Request::create('posts/1/edit', 'GET'));

    expect($response->content())->toBe('success');
});

test('model instance as parameter', function () {
    $instance = new class extends Model {};

    $this->container->make(GateContract::class)->define('success', function ($user, $model) use ($instance) {
        expect($instance)->toBe($model);

        return true;
    });

    $request = new Request;

    $next = function () {
        //
    };

    (new Authorize($this->container->make(GateContract::class)))
        ->handle($request, $next, 'success', $instance);
});
