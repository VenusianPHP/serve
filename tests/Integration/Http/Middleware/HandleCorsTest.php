<?php

namespace Venusian\Serve\Tests\Integration\Http\Middleware;

use Mariner\Core\Http\Kernel;
use Mariner\Http\Middleware\HandleCors;
use Mariner\Http\Request;
use Mariner\Routing\Router;

beforeEach(function () {
    $app = $this->app;
    $app['config']['cors'] = [
        'paths' => ['api/*'],
        'supports_credentials' => false,
        'allowed_origins' => ['http://localhost'],
        'allowed_headers' => ['X-Custom-1', 'X-Custom-2'],
        'allowed_methods' => ['GET', 'POST'],
        'exposed_headers' => [],
        'max_age' => 0,
    ];

    $kernel = $app->make(Kernel::class);
    $kernel->prependMiddleware(HandleCors::class);
});

beforeEach(function () {
    $router = $this->app['router'];
    addWebRoutes($router);
    addApiRoutes($router);
});

test('should return header assess control allow origin when dont have http origin on request', function () {
    $crawler = $this->call('OPTIONS', 'api/ping', [], [], [], [
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($crawler->headers->get('Access-Control-Allow-Origin'))->toBe('http://localhost');
    expect($crawler->getStatusCode())->toEqual(204);
});

test('options allow origin allowed', function () {
    $crawler = $this->call('OPTIONS', 'api/ping', [], [], [], [
        'HTTP_ORIGIN' => 'http://localhost',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($crawler->headers->get('Access-Control-Allow-Origin'))->toBe('http://localhost');
    expect($crawler->getStatusCode())->toEqual(204);
});

test('allow all origins', function () {
    $this->app['config']->set('cors.allowed_origins', ['*']);

    $crawler = $this->call('OPTIONS', 'api/ping', [], [], [], [
        'HTTP_ORIGIN' => 'http://laravel.com',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($crawler->headers->get('Access-Control-Allow-Origin'))->toBe('*');
    expect($crawler->getStatusCode())->toEqual(204);
});

test('allow all origins wildcard', function () {
    $this->app['config']->set('cors.allowed_origins', ['*.laravel.com']);

    $crawler = $this->call('OPTIONS', 'api/ping', [], [], [], [
        'HTTP_ORIGIN' => 'http://test.laravel.com',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($crawler->headers->get('Access-Control-Allow-Origin'))->toBe('http://test.laravel.com');
    expect($crawler->getStatusCode())->toEqual(204);
});

test('origins wildcard includes nested subdomains', function () {
    $this->app['config']->set('cors.allowed_origins', ['*.laravel.com']);

    $crawler = $this->call('OPTIONS', 'api/ping', [], [], [], [
        'HTTP_ORIGIN' => 'http://api.service.test.laravel.com',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($crawler->headers->get('Access-Control-Allow-Origin'))->toBe('http://api.service.test.laravel.com');
    expect($crawler->getStatusCode())->toEqual(204);
});

test('allow all origins wildcard no match', function () {
    $this->app['config']->set('cors.allowed_origins', ['*.laravel.com']);

    $crawler = $this->call('OPTIONS', 'api/ping', [], [], [], [
        'HTTP_ORIGIN' => 'http://test.symfony.com',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($crawler->headers->get('Access-Control-Allow-Origin'))->toEqual(null);
});

test('options allow origin allowed non existing route', function () {
    $crawler = $this->call('OPTIONS', 'api/pang', [], [], [], [
        'HTTP_ORIGIN' => 'http://localhost',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($crawler->headers->get('Access-Control-Allow-Origin'))->toBe('http://localhost');
    expect($crawler->getStatusCode())->toEqual(204);
});

test('options allow origin not allowed', function () {
    $crawler = $this->call('OPTIONS', 'api/ping', [], [], [], [
        'HTTP_ORIGIN' => 'http://otherhost',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($crawler->headers->get('Access-Control-Allow-Origin'))->toBe('http://localhost');
});

test('allow method allowed', function () {
    $crawler = $this->call('POST', 'web/ping', [], [], [], [
        'HTTP_ORIGIN' => 'http://localhost',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);
    expect($crawler->headers->get('Access-Control-Allow-Methods'))->toEqual(null);
    expect($crawler->getStatusCode())->toEqual(200);

    expect($crawler->getContent())->toBe('PONG');
});

test('allow method not allowed', function () {
    $crawler = $this->call('POST', 'web/ping', [], [], [], [
        'HTTP_ORIGIN' => 'http://localhost',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'PUT',
    ]);
    expect($crawler->headers->get('Access-Control-Allow-Methods'))->toEqual(null);
    expect($crawler->getStatusCode())->toEqual(200);
});

test('allow header allowed options', function () {
    $crawler = $this->call('OPTIONS', 'api/ping', [], [], [], [
        'HTTP_ORIGIN' => 'http://localhost',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-custom-1, x-custom-2',
    ]);
    expect($crawler->headers->get('Access-Control-Allow-Headers'))->toBe('x-custom-1, x-custom-2');
    expect($crawler->getStatusCode())->toEqual(204);

    expect($crawler->getContent())->toBe('');
});

test('allow header allowed wildcard options', function () {
    $this->app['config']->set('cors.allowed_headers', ['*']);

    $crawler = $this->call('OPTIONS', 'api/ping', [], [], [], [
        'HTTP_ORIGIN' => 'http://localhost',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-custom-3',
    ]);
    expect($crawler->headers->get('Access-Control-Allow-Headers'))->toBe('x-custom-3');
    expect($crawler->getStatusCode())->toEqual(204);

    expect($crawler->getContent())->toBe('');
});

test('allow header not allowed options', function () {
    $crawler = $this->call('OPTIONS', 'api/ping', [], [], [], [
        'HTTP_ORIGIN' => 'http://localhost',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-custom-3',
    ]);
    expect($crawler->headers->get('Access-Control-Allow-Headers'))->toBe('x-custom-1, x-custom-2');
});

test('allow header allowed', function () {
    $crawler = $this->call('POST', 'web/ping', [], [], [], [
        'HTTP_ORIGIN' => 'http://localhost',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-custom-1, x-custom-2',
    ]);
    expect($crawler->headers->get('Access-Control-Allow-Headers'))->toEqual(null);
    expect($crawler->getStatusCode())->toEqual(200);

    expect($crawler->getContent())->toBe('PONG');
});

test('allow header allowed wildcard', function () {
    $this->app['config']->set('cors.allowed_headers', ['*']);

    $crawler = $this->call('POST', 'web/ping', [], [], [], [
        'HTTP_ORIGIN' => 'http://localhost',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-custom-3',
    ]);
    expect($crawler->headers->get('Access-Control-Allow-Headers'))->toEqual(null);
    expect($crawler->getStatusCode())->toEqual(200);

    expect($crawler->getContent())->toBe('PONG');
});

test('allow header not allowed', function () {
    $crawler = $this->call('POST', 'web/ping', [], [], [], [
        'HTTP_ORIGIN' => 'http://localhost',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-custom-3',
    ]);
    expect($crawler->headers->get('Access-Control-Allow-Headers'))->toEqual(null);
    expect($crawler->getStatusCode())->toEqual(200);
});

test('error', function () {
    $crawler = $this->call('POST', 'api/error', [], [], [], [
        'HTTP_ORIGIN' => 'http://localhost',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);

    expect($crawler->headers->get('Access-Control-Allow-Origin'))->toBe('http://localhost');
    expect($crawler->getStatusCode())->toEqual(500);
});

test('validation exception', function () {
    $crawler = $this->call('POST', 'api/validation', [], [], [], [
        'HTTP_ORIGIN' => 'http://localhost',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
    ]);
    expect($crawler->headers->get('Access-Control-Allow-Origin'))->toBe('http://localhost');
    expect($crawler->getStatusCode())->toEqual(302);
});

function addWebRoutes(Router $router)
{
    $router->post('web/ping', [
        'uses' => function () {
            return 'PONG';
        },
    ]);
}

function addApiRoutes(Router $router)
{
    $router->post('api/ping', [
        'uses' => function () {
            return 'PONG';
        },
    ]);

    $router->put('api/ping', [
        'uses' => function () {
            return 'PONG';
        },
    ]);

    $router->post('api/error', [
        'uses' => function () {
            abort(500);
        },
    ]);

    $router->post('api/validation', [
        'uses' => function (Request $request) {
            $request->validate([
                'name' => 'required',
            ]);

            return 'ok';
        },
    ]);
}
