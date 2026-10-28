<?php

namespace Venusian\Serve\Tests\Routing;

use Mariner\Routing\Concerns\BindsToRoutes;
use Mariner\Contracts\Routing\UrlRoutable;
use Voyager\Database\Instrument\Model;
use Mariner\Http\Request;
use Mariner\Routing\EncodedParameter;
use Mariner\Routing\Exceptions\UrlGenerationException;
use Mariner\Routing\Route;
use Mariner\Routing\RouteCollection;
use Mariner\Routing\UrlGenerator;
use Venusian\Serve\Tests\Routing\Fixtures\CategoryBackedEnum;
use Venusian\Serve\Tests\Routing\Fixtures\CategoryEnum;
use Venusian\Serve\Tests\Routing\Fixtures\RouteDomainEnum;
use Venusian\Serve\Tests\Routing\Fixtures\RouteNameEnum;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\Routing\Exception\RouteNotFoundException;

require_once __DIR__.'/Fixtures/Enums.php';

test('basic generation', function () {
    $url = new UrlGenerator(
        new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    expect($url->to('foo/bar'))->toBe('http://www.foo.com/foo/bar');
    expect($url->to('foo/bar', [], true))->toBe('https://www.foo.com/foo/bar');
    expect($url->to('foo/bar', ['baz', 'boom'], true))->toBe('https://www.foo.com/foo/bar/baz/boom');
    expect($url->to('foo/bar?foo=bar', ['baz'], true))->toBe('https://www.foo.com/foo/bar/baz?foo=bar');

    // Test HTTPS request URL generation...
    $url = new UrlGenerator(
        new RouteCollection,
        Request::create('https://www.foo.com/')
    );

    expect($url->to('foo/bar'))->toBe('https://www.foo.com/foo/bar');
});

test('query generation', function () {
    $url = new UrlGenerator(
        new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    expect($url->query('foo/bar'))->toBe('http://www.foo.com/foo/bar');
    expect($url->query('foo/bar', ['foo']))->toBe('http://www.foo.com/foo/bar?0=foo');
    expect($url->query('foo/bar', ['baz' => 'boom']))->toBe('http://www.foo.com/foo/bar?baz=boom');
    expect($url->query('foo/bar?baz=boom&zal=bee', ['baz' => 'zee']))->toBe('http://www.foo.com/foo/bar?baz=zee&zal=bee');
    expect($url->query('foo/bar?baz=boom&zal=bee', ['baz' => null]))->toBe('http://www.foo.com/foo/bar?zal=bee');
    expect($url->query('foo/bar?baz=boom', ['nonexist' => null]))->toBe('http://www.foo.com/foo/bar?baz=boom');
    expect($url->query('foo/bar?baz=boom', ['baz' => null]))->toBe('http://www.foo.com/foo/bar');
    expect($url->query('foo/bar?foo=bar', ['zal' => 'bee'], ['baz'], true))->toBe('https://www.foo.com/foo/bar/baz?foo=bar&zal=bee');
    expect(urldecode($url->query('foo/bar', ['baz' => ['boom', 'bam', 'bim']])))->toBe('http://www.foo.com/foo/bar?baz[0]=boom&baz[1]=bam&baz[2]=bim');
});

test('asset generation', function () {
    $url = new UrlGenerator(
        new RouteCollection,
        Request::create('http://www.foo.com/index.php/')
    );

    expect($url->asset('foo/bar'))->toBe('http://www.foo.com/foo/bar');
    expect($url->asset('foo/bar', true))->toBe('https://www.foo.com/foo/bar');

    $url = new UrlGenerator(
        new RouteCollection,
        Request::create('http://www.foo.com/index.php/'),
        '/'
    );

    expect($url->asset('foo/bar'))->toBe('/foo/bar');
    expect($url->asset('foo/bar', true))->toBe('/foo/bar');
});

test('basic generation with host formatting', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $route = new Route(['GET'], '/named-route', ['as' => 'plain']);
    $routes->add($route);

    $url->formatHostUsing(function ($host) {
        return str_replace('foo.com', 'foo.org', $host);
    });

    expect($url->to('foo/bar'))->toBe('http://www.foo.org/foo/bar');
    expect($url->route('plain', [], false))->toBe('/named-route');
});

test('basic generation with request base url with subfolder', function () {
    $request = Request::create('http://www.foo.com/subfolder/foo/bar/subfolder/');

    $request->server->set('SCRIPT_FILENAME', '/var/www/laravel-project/public/subfolder/index.php');
    $request->server->set('PHP_SELF', '/subfolder/index.php');

    $url = new UrlGenerator(
        $routes = new RouteCollection,
        $request
    );

    $route = new Route(['GET'], 'foo/bar/subfolder', ['as' => 'foobar']);
    $routes->add($route);

    expect($request->getBaseUrl())->toBe('/subfolder');
    expect($url->route('foobar', [], false))->toBe('/foo/bar/subfolder');
});

test('basic generation with request base url with subfolder and file suffix', function () {
    $request = Request::create('http://www.foo.com/subfolder/index.php');

    $request->server->set('SCRIPT_FILENAME', '/var/www/laravel-project/public/subfolder/index.php');
    $request->server->set('PHP_SELF', '/subfolder/index.php');

    $url = new UrlGenerator(
        $routes = new RouteCollection,
        $request
    );

    $route = new Route(['GET'], 'foo/bar/subfolder', ['as' => 'foobar']);
    $routes->add($route);

    expect($request->getBasePath())->toBe('/subfolder');
    expect($request->getBaseUrl())->toBe('/subfolder/index.php');
    expect($url->route('foobar', [], false))->toBe('/foo/bar/subfolder');
});

test('basic generation with request base url with file suffix', function () {
    $request = Request::create('http://www.foo.com/other.php');

    $request->server->set('SCRIPT_FILENAME', '/var/www/laravel-project/public/other.php');
    $request->server->set('PHP_SELF', '/other.php');

    $url = new UrlGenerator(
        $routes = new RouteCollection,
        $request
    );

    $route = new Route(['GET'], 'foo/bar/subfolder', ['as' => 'foobar']);
    $routes->add($route);

    expect($request->getBasePath())->toBe('');
    expect($request->getBaseUrl())->toBe('/other.php');
    expect($url->route('foobar', [], false))->toBe('/foo/bar/subfolder');
});

test('basic generation with path formatting', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $route = new Route(['GET'], '/named-route', ['as' => 'plain']);
    $routes->add($route);

    $url->formatPathUsing(function ($path) {
        return '/something'.$path;
    });

    expect($url->to('foo/bar'))->toBe('http://www.foo.com/something/foo/bar');
    expect($url->route('plain', [], false))->toBe('/something/named-route');
});

test('url formatters should receive target route', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://abc.com/')
    );

    $namedRoute = new Route(['GET'], '/bar', ['as' => 'plain', 'root' => 'bar.com', 'path' => 'foo']);
    $routes->add($namedRoute);

    $url->formatHostUsing(function ($root, $route) {
        return $route ? 'http://'.$route->getAction('root') : $root;
    });

    $url->formatPathUsing(function ($path, $route) {
        return $route ? '/'.$route->getAction('path') : $path;
    });

    expect($url->to('foo/bar'))->toBe('http://abc.com/foo/bar');
    expect($url->route('plain'))->toBe('http://bar.com/foo');
});

test('basic route generation', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    // Empty Named Route
    $route = new Route(['GET'], '/', ['as' => 'plain']);
    $routes->add($route);

    // Named Routes
    $route = new Route(['GET'], 'foo/bar', ['as' => 'foo']);
    $routes->add($route);

    // Parameters...
    $route = new Route(['GET'], 'foo/bar/{baz}/breeze/{boom}', ['as' => 'bar']);
    $routes->add($route);

    // Single Parameter...
    $route = new Route(['GET'], 'foo/bar/{baz}', ['as' => 'foobar']);
    $routes->add($route);

    // Optional parameter
    $route = new Route(['GET'], 'foo/bar/{baz?}', ['as' => 'optional']);
    $routes->add($route);

    // HTTPS...
    $route = new Route(['GET'], 'foo/baz', ['as' => 'baz', 'https']);
    $routes->add($route);

    // Controller Route Route
    $route = new Route(['GET'], 'foo/bam', ['controller' => 'foo@bar']);
    $routes->add($route);

    // Non ASCII routes
    $route = new Route(['GET'], 'foo/bar/åαф/{baz}', ['as' => 'foobarbaz']);
    $routes->add($route);

    // Fragments
    $route = new Route(['GET'], 'foo/bar#derp', ['as' => 'fragment']);
    $routes->add($route);

    // Invoke action
    $route = new Route(['GET'], 'foo/invoke', ['controller' => 'InvokableActionStub']);
    $routes->add($route);

    // With Default Parameter
    $url->defaults(['locale' => 'en']);
    $route = new Route(['GET'], 'foo', ['as' => 'defaults', 'domain' => '{locale}.example.com', function () {
        //
    }]);
    $routes->add($route);

    // With backed enum name and domain
    $route = (new Route(['GET'], 'backed-enum', ['as' => 'prefixed.']))->name(RouteNameEnum::UserIndex)->domain(RouteDomainEnum::DashboardDomain);
    $routes->add($route);

    expect($url->route('plain', [], false))->toBe('/');
    expect($url->route('plain', ['foo' => 'bar'], false))->toBe('/?foo=bar');
    expect($url->route('foo'))->toBe('http://www.foo.com/foo/bar');
    expect($url->route('foo', [], false))->toBe('/foo/bar');
    expect($url->route('foo', ['foo' => 'bar'], false))->toBe('/foo/bar?foo=bar');
    expect($url->route('bar', ['taylor', 'otwell', 'fly' => 'wall']))->toBe('http://www.foo.com/foo/bar/taylor/breeze/otwell?fly=wall');
    expect($url->route('bar', ['boom' => 'taylor', 'baz' => 'otwell', 'fly' => 'wall']))->toBe('http://www.foo.com/foo/bar/otwell/breeze/taylor?fly=wall');
    expect($url->route('foobar', 0))->toBe('http://www.foo.com/foo/bar/0');
    expect($url->route('foobar', 2))->toBe('http://www.foo.com/foo/bar/2');
    expect($url->route('foobar', 'taylor'))->toBe('http://www.foo.com/foo/bar/taylor');
    expect($url->route('bar', ['taylor', 'otwell', 'fly' => 'wall'], false))->toBe('/foo/bar/taylor/breeze/otwell?fly=wall');
    expect($url->route('baz'))->toBe('https://www.foo.com/foo/baz');
    expect($url->action('foo@bar'))->toBe('http://www.foo.com/foo/bam');
    expect($url->action(['foo', 'bar']))->toBe('http://www.foo.com/foo/bam');
    expect($url->action('InvokableActionStub'))->toBe('http://www.foo.com/foo/invoke');
    expect($url->route('bar', ['wall', 'woz', 'boom' => 'otwell', 'baz' => 'taylor']))->toBe('http://www.foo.com/foo/bar/taylor/breeze/otwell?wall&woz');
    expect($url->route('bar', ['taylor', 'otwell', 'wall', 'woz']))->toBe('http://www.foo.com/foo/bar/taylor/breeze/otwell?wall&woz');
    expect($url->route('optional'))->toBe('http://www.foo.com/foo/bar');
    expect($url->route('optional', ['baz' => null]))->toBe('http://www.foo.com/foo/bar');
    expect($url->route('optional', ['baz' => '']))->toBe('http://www.foo.com/foo/bar');
    expect($url->route('optional', ['baz' => 0]))->toBe('http://www.foo.com/foo/bar/0');
    expect($url->route('optional', 'taylor'))->toBe('http://www.foo.com/foo/bar/taylor');
    expect($url->route('optional', ['taylor']))->toBe('http://www.foo.com/foo/bar/taylor');
    expect($url->route('optional', ['taylor', 'breeze']))->toBe('http://www.foo.com/foo/bar/taylor?breeze');
    expect($url->route('optional', ['wall' => 'woz', 'taylor']))->toBe('http://www.foo.com/foo/bar/taylor?wall=woz');
    expect($url->route('optional', ['wall' => 'woz', 'breeze', 'baz' => 'taylor']))->toBe('http://www.foo.com/foo/bar/taylor?wall=woz&breeze');
    expect($url->route('optional', ['wall' => 'woz']))->toBe('http://www.foo.com/foo/bar?wall=woz');
    expect($url->route('foobarbaz', ['baz' => 'åαф']))->toBe('http://www.foo.com/foo/bar/%C3%A5%CE%B1%D1%84/%C3%A5%CE%B1%D1%84');
    expect($url->route('fragment', [], false))->toBe('/foo/bar#derp');
    expect($url->route('fragment', ['foo' => 'bar'], false))->toBe('/foo/bar?foo=bar#derp');
    expect($url->route('fragment', ['baz' => 'åαф'], false))->toBe('/foo/bar?baz=%C3%A5%CE%B1%D1%84#derp');
    expect($url->route('defaults'))->toBe('http://en.example.com/foo');
    expect($url->route('prefixed.users.index'))->toBe('http://dashboard.myapp.com/backed-enum');
});

test('fluent route name definitions', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    // Named Routes
    $route = new Route(['GET'], 'foo/bar', []);
    $route->name('foo');
    $routes->add($route);
    $routes->refreshNameLookups();

    expect($url->route('foo'))->toBe('http://www.foo.com/foo/bar');
});

test('controller routes with adefault namespace', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $url->setRootControllerNamespace('namespace');

    // Controller Route Route
    $route = new Route(['GET'], 'foo/bar', ['controller' => 'namespace\foo@bar']);
    $routes->add($route);

    $route = new Route(['GET'], 'something/else', ['controller' => 'something\foo@bar']);
    $routes->add($route);

    $route = new Route(['GET'], 'foo/invoke', ['controller' => 'namespace\InvokableActionStub']);
    $routes->add($route);

    expect($url->action('foo@bar'))->toBe('http://www.foo.com/foo/bar');
    expect($url->action('\something\foo@bar'))->toBe('http://www.foo.com/something/else');
    expect($url->action('InvokableActionStub'))->toBe('http://www.foo.com/foo/invoke');
});

test('controller routes outside of default namespace', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $url->setRootControllerNamespace('namespace');

    $route = new Route(['GET'], 'root/namespace', ['controller' => '\root\namespace@foo']);
    $routes->add($route);

    $route = new Route(['GET'], 'invokable/namespace', ['controller' => '\root\namespace\InvokableActionStub']);
    $routes->add($route);

    expect($url->action('\root\namespace@foo'))->toBe('http://www.foo.com/root/namespace');
    expect($url->action('\root\namespace\InvokableActionStub'))->toBe('http://www.foo.com/invokable/namespace');
});

test('routable interface routing', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $route = new Route(['GET'], 'foo/{bar}', ['as' => 'routable']);
    $routes->add($route);

    $model = new RoutableInterfaceStub;
    $model->key = 'routable';

    expect($url->route('routable', [$model], false))->toBe('/foo/routable');
});

test('routable interface routing with custom binding field', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $route = new Route(['GET'], 'foo/{bar:slug}', ['as' => 'routable']);
    $routes->add($route);

    $model = new RoutableInterfaceStub;
    $model->key = 'routable';

    expect($url->route('routable', ['bar' => $model], false))->toBe('/foo/test-slug');
    expect($url->route('routable', [$model], false))->toBe('/foo/test-slug');
});

test('routable interface routing as query string', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $route = new Route(['GET'], 'foo', ['as' => 'query-string']);
    $routes->add($route);

    $model = new RoutableInterfaceStub;
    $model->key = 'routable';

    expect($url->route('query-string', $model, false))->toBe('/foo?routable');
    expect($url->route('query-string', [$model], false))->toBe('/foo?routable');
    expect($url->route('query-string', ['foo' => $model], false))->toBe('/foo?foo=routable');
});

test('routable interface routing with separate binding field only for second parameter', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $route = new Route(['GET'], 'foo/{bar}/{baz:slug}', ['as' => 'routable']);
    $routes->add($route);

    $model1 = new RoutableInterfaceStub;
    $model1->key = 'routable-1';

    $model2 = new RoutableInterfaceStub;
    $model2->key = 'routable-2';

    expect($url->route('routable', ['bar' => $model1, 'baz' => $model2], false))->toBe('/foo/routable-1/test-slug');
    expect($url->route('routable', [$model1, $model2], false))->toBe('/foo/routable-1/test-slug');
});

test('routable interface routing with single parameter', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $route = new Route(['GET'], 'foo/{bar}', ['as' => 'routable']);
    $routes->add($route);

    $model = new RoutableInterfaceStub;
    $model->key = 'routable';

    expect($url->route('routable', $model, false))->toBe('/foo/routable');
});

test('routes maintain request scheme', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('https://www.foo.com/')
    );

    // Named Routes
    $route = new Route(['GET'], 'foo/bar', ['as' => 'foo']);
    $routes->add($route);

    expect($url->route('foo'))->toBe('https://www.foo.com/foo/bar');
});

test('http only routes', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('https://www.foo.com/')
    );

    // Named Routes
    $route = new Route(['GET'], 'foo/bar', ['as' => 'foo', 'http']);
    $routes->add($route);

    expect($url->route('foo'))->toBe('http://www.foo.com/foo/bar');
});

test('routes with domains', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $route = new Route(['GET'], 'foo/bar', ['as' => 'foo', 'domain' => 'sub.foo.com']);
    $routes->add($route);

    // Wildcards & Domains...
    $route = new Route(['GET'], 'foo/bar/{baz}', ['as' => 'bar', 'domain' => 'sub.{foo}.com']);
    $routes->add($route);

    expect($url->route('foo'))->toBe('http://sub.foo.com/foo/bar');
    expect($url->route('bar', ['taylor', 'otwell']))->toBe('http://sub.taylor.com/foo/bar/otwell');
    expect($url->route('bar', ['taylor', 'otwell'], false))->toBe('/foo/bar/otwell');
});

test('routes with domains and ports', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com:8080/')
    );

    $route = new Route(['GET'], 'foo/bar', ['as' => 'foo', 'domain' => 'sub.foo.com']);
    $routes->add($route);

    // Wildcards & Domains...
    $route = new Route(['GET'], 'foo/bar/{baz}', ['as' => 'bar', 'domain' => 'sub.{foo}.com']);
    $routes->add($route);

    expect($url->route('foo'))->toBe('http://sub.foo.com:8080/foo/bar');
    expect($url->route('bar', ['taylor', 'otwell']))->toBe('http://sub.taylor.com:8080/foo/bar/otwell');
});

test('routes with domains strips protocols', function () {
    // http:// Route
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $route = new Route(['GET'], 'foo/bar', ['as' => 'foo', 'domain' => 'http://sub.foo.com']);
    $routes->add($route);

    expect($url->route('foo'))->toBe('http://sub.foo.com/foo/bar');

    // https:// Route
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('https://www.foo.com/')
    );

    $route = new Route(['GET'], 'foo/bar', ['as' => 'foo', 'domain' => 'https://sub.foo.com']);
    $routes->add($route);

    expect($url->route('foo'))->toBe('https://sub.foo.com/foo/bar');
});

test('https routes with domains', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('https://foo.com/')
    );

    // When on HTTPS, no need to specify 443
    $route = new Route(['GET'], 'foo/bar', ['as' => 'baz', 'domain' => 'sub.foo.com']);
    $routes->add($route);

    expect($url->route('baz'))->toBe('https://sub.foo.com/foo/bar');
});

test('routes with domains through proxy', function () {
    Request::setTrustedProxies(['10.0.0.1'], SymfonyRequest::HEADER_X_FORWARDED_FOR | SymfonyRequest::HEADER_X_FORWARDED_HOST | SymfonyRequest::HEADER_X_FORWARDED_PORT | SymfonyRequest::HEADER_X_FORWARDED_PROTO);

    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/', 'GET', [], [], [], ['REMOTE_ADDR' => '10.0.0.1', 'HTTP_X_FORWARDED_PORT' => '80'])
    );

    $route = new Route(['GET'], 'foo/bar', ['as' => 'foo', 'domain' => 'sub.foo.com']);
    $routes->add($route);

    expect($url->route('foo'))->toBe('http://sub.foo.com/foo/bar');
});

dataset('providerRouteParameters', function () {
    return [
        [['test' => 123]],
        [['one' => null, 'test' => 123]],
        [['one' => '', 'test' => 123]],
    ];
});

test('url generation for controllers requires passing of required parameters', function ($parameters) {

    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com:8080/')
    );

    $route = new Route(['GET'], 'foo/{one}/{two?}/{three?}', ['as' => 'foo', function () {
        //
    }]);
    $routes->add($route);

    expect($url->route('foo', $parameters))->toBe('http://www.foo.com:8080/foo?test=123');
})->with('providerRouteParameters')->throws(UrlGenerationException::class);

dataset('provideParametersAndExpectedMeaningfulExceptionMessages', function () {
    return [
        'Missing parameters "one", "two" and "three"' => [
            [],
            'Missing required parameters for [Route: foo] [URI: foo/{one}/{two}/{three}/{four?}] [Missing parameters: one, two, three].',
        ],
        'Missing parameters "two" and "three"' => [
            ['one' => '123'],
            'Missing required parameters for [Route: foo] [URI: foo/{one}/{two}/{three}/{four?}] [Missing parameters: two, three].',
        ],
        'Missing parameters "one" and "three"' => [
            ['two' => '123'],
            'Missing required parameters for [Route: foo] [URI: foo/{one}/{two}/{three}/{four?}] [Missing parameters: one, three].',
        ],
        'Missing parameters "one" and "two"' => [
            ['three' => '123'],
            'Missing required parameters for [Route: foo] [URI: foo/{one}/{two}/{three}/{four?}] [Missing parameters: one, two].',
        ],
        'Missing parameter "three"' => [
            ['one' => '123', 'two' => '123'],
            'Missing required parameter for [Route: foo] [URI: foo/{one}/{two}/{three}/{four?}] [Missing parameter: three].',
        ],
        'Missing parameter "two"' => [
            ['one' => '123', 'three' => '123'],
            'Missing required parameter for [Route: foo] [URI: foo/{one}/{two}/{three}/{four?}] [Missing parameter: two].',
        ],
        'Missing parameter "one"' => [
            ['two' => '123', 'three' => '123'],
            'Missing required parameter for [Route: foo] [URI: foo/{one}/{two}/{three}/{four?}] [Missing parameter: one].',
        ],
    ];
});

test('url generation throws exception for missing parameters with meaningful message', function ($parameters, $expectedMeaningfulExceptionMessage) {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com:8080/')
    );

    $route = new Route(['GET'], 'foo/{one}/{two}/{three}/{four?}', ['as' => 'foo', function () {
        //
    }]);
    $routes->add($route);

    expect(fn () => $url->route('foo', $parameters))->toThrow(UrlGenerationException::class, $expectedMeaningfulExceptionMessage);
})->with('provideParametersAndExpectedMeaningfulExceptionMessages');

test('set asset url', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $url->useOrigin('https://www.bar.com');
    expect($url->to('foo/bar'))->toBe('http://www.bar.com/foo/bar');
    expect($url->asset('foo/bar'))->toBe('http://www.bar.com/foo/bar');

    $url->useAssetOrigin('https://www.foo.com');
    expect($url->to('foo/bar'))->not->toBe('https://www.foo.com/foo/bar');
    expect($url->asset('foo/bar'))->toBe('https://www.foo.com/foo/bar');
    expect($url->asset('foo/bar', true))->toBe('https://www.foo.com/foo/bar');
    expect($url->asset('foo/bar', false))->toBe('https://www.foo.com/foo/bar');
});

test('use root url', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $url->useOrigin('https://www.bar.com');
    expect($url->to('foo/bar'))->toBe('http://www.bar.com/foo/bar');

    // Ensure trailing slash is trimmed from root URL as UrlGenerator already handles this
    $url->useOrigin('http://www.foo.com/');
    expect($url->to('/bar'))->toBe('http://www.foo.com/bar');

    // Route Based...
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $url->forceScheme('https');
    $route = new Route(['GET'], '/foo', ['as' => 'plain']);
    $routes->add($route);

    expect($url->route('plain'))->toBe('https://www.foo.com/foo');

    $url->useOrigin('https://www.bar.com');
    expect($url->route('plain'))->toBe('https://www.bar.com/foo');
});

test('force https', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $url->forceHttps();
    $route = new Route(['GET'], '/foo', ['as' => 'plain']);
    $routes->add($route);

    expect($url->route('plain'))->toBe('https://www.foo.com/foo');
});

test('previous', function () {
    $url = new UrlGenerator(
        new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $url->getRequest()->headers->set('referer', 'http://www.bar.com/');
    expect($url->previous())->toBe('http://www.bar.com/');

    $url->getRequest()->headers->remove('referer');
    expect($url->previous())->toEqual($url->to('/'));

    expect($url->previous('/foo'))->toEqual($url->to('/foo'));
});

test('previous path', function () {
    $url = new UrlGenerator(
        new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $url->getRequest()->headers->set('referer', 'http://www.foo.com?baz=bah');
    expect($url->previousPath())->toBe('/');

    $url->getRequest()->headers->set('referer', 'http://www.foo.com/?baz=bah');
    expect($url->previousPath())->toBe('/');

    $url->getRequest()->headers->set('referer', 'http://www.foo.com/bar?baz=bah');
    expect($url->previousPath())->toBe('/bar');

    $url->getRequest()->headers->set('referer', 'http://www.bar.com/foo');
    expect($url->previousPath())->toBe('/foo');

    $url->getRequest()->headers->set('referer', 'http://www.bar.com/foo?bar=baz');
    expect($url->previousPath())->toBe('/foo');

    $url->getRequest()->headers->set('referer', 'http://www.bar.com');
    expect($url->previousPath())->toBe('/');

    $url->getRequest()->headers->remove('referer');
    expect($url->previousPath())->toBe('/');

    expect($url->previousPath('/bar'))->toBe('/bar');
});

test('previous path with base url', function () {
    $url = new UrlGenerator(
        new RouteCollection,
        Request::create('http://www.foo.com/subdir/current')
    );

    $url->forceRootUrl('http://www.foo.com/subdir');

    $url->getRequest()->headers->set('referer', 'http://www.foo.com/subdir/dashboard?x=1');
    expect($url->previousPath())->toBe('/dashboard');

    $url->getRequest()->headers->set('referer', 'http://www.bar.com/foo');
    expect($url->previousPath())->toBe('/foo');

    $url->getRequest()->headers->set('referer', 'http://www.foo.com/subdir');
    expect($url->previousPath())->toBe('/');
});

test('route not defined exception', function () {

    $url = new UrlGenerator(
        new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $url->route('not_exists_route');
})->throws(RouteNotFoundException::class, 'Route [not_exists_route] not defined.');

test('route parameters containing percent signs are encoded', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        $request = Request::create('http://www.foo.com/')
    );

    $routes->add(new Route(['GET'], 'foo/{bar}', ['as' => 'foo', function () {
        //
    }]));

    // A raw percent sign would be decoded again when the router matches the URL,
    // so the parameter has to survive a round trip through rawurldecode()...
    expect($url->route('foo', ['bar' => '%66oo']))->toBe('http://www.foo.com/foo/%2566oo');
    expect($url->route('foo', ['bar' => '100%']))->toBe('http://www.foo.com/foo/100%25');

    expect(rawurldecode('%2566oo'))->toBe('%66oo');
    expect(rawurldecode('100%25'))->toBe('100%');

    // Values without a percent sign are unaffected...
    expect($url->route('foo', ['bar' => 'bar']))->toBe('http://www.foo.com/foo/bar');
    expect($url->route('foo', ['bar' => 1]))->toBe('http://www.foo.com/foo/1');
});

test('encoded route parameters are not encoded again', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        $request = Request::create('http://www.foo.com/')
    );

    $routes->add(new Route(['GET'], 'foo/{bar}', ['as' => 'foo', function () {
        //
    }]));

    // Values that are already URL encoded may be marked as such so they are used as-is...
    expect($url->route('foo', ['bar' => new EncodedParameter('foo%20bar')]))->toBe('http://www.foo.com/foo/foo%20bar');
    expect($url->route('foo', ['bar' => new EncodedParameter('%66oo')]))->toBe('http://www.foo.com/foo/%66oo');

    // While plain strings continue to be encoded...
    expect($url->route('foo', ['bar' => 'foo%20bar']))->toBe('http://www.foo.com/foo/foo%2520bar');
});

test('signed url', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        $request = Request::create('http://www.foo.com/')
    );
    $url->setKeyResolver(function () {
        return 'secret';
    });

    $route = new Route(['GET'], 'foo', ['as' => 'foo', function () {
        //
    }]);
    $routes->add($route);

    $request = Request::create($url->signedRoute('foo'));

    expect($url->hasValidSignature($request))->toBeTrue();

    $request = Request::create($url->signedRoute('foo').'?tampered=true');

    expect($url->hasValidSignature($request))->toBeFalse();

    $request = Request::create($url->signedRoute('foo').'&tampered=true');

    expect($url->hasValidSignature($request, ignoreQuery: ['tampered']))->toBeTrue();

    expect($url->hasValidSignature($request, ignoreQuery: fn ($parameter) => $parameter === 'tampered'))->toBeTrue();
});

test('signed url does not trust forwarded prefix to change the path being verified', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );
    $url->setKeyResolver(fn () => 'secret');

    $routes->add(new Route(['GET'], 'document/{document}', ['as' => 'document.show', function () {
        //
    }]));

    $signedUrl = $url->signedRoute('document.show', ['document' => 1]);

    $request = Request::create(
        'http://www.foo.com/admin/42?'.parse_url($signedUrl, PHP_URL_QUERY),
        'GET', [], [], [], [
            'REMOTE_ADDR' => '127.0.0.1',
            'HTTP_X_FORWARDED_PREFIX' => '/document/1?',
        ]
    );
    $request::setTrustedProxies(['127.0.0.1'], Request::HEADER_X_FORWARDED_PREFIX);

    try {
        expect($url->hasValidSignature($request))->toBeFalse();
    } finally {
        $request::setTrustedProxies([], Request::HEADER_X_FORWARDED_PREFIX);
    }
});

test('signed url implicit model binding', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        $request = Request::create('http://www.foo.com/')
    );
    $url->setKeyResolver(function () {
        return 'secret';
    });

    $route = new Route(['GET'], 'foo/{user:uuid}', ['as' => 'foo', function () {
        //
    }]);
    $routes->add($route);

    $user = new RoutingUrlGeneratorTestUser(['uuid' => '0231d4ac-e9e3-4452-a89a-4427cfb23c3e']);

    $request = Request::create($url->signedRoute('foo', $user));

    expect($url->hasValidSignature($request))->toBeTrue();
});

test('signed relative url', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        $request = Request::create('http://www.foo.com/')
    );
    $url->setKeyResolver(function () {
        return 'secret';
    });

    $route = new Route(['GET'], 'foo', ['as' => 'foo', function () {
        //
    }]);
    $routes->add($route);

    $result = $url->signedRoute('foo', [], null, false);

    $request = Request::create($result);

    expect($url->hasValidSignature($request, false))->toBeTrue();

    $request = Request::create($url->signedRoute('foo', [], null, false).'?tampered=true');

    expect($url->hasValidSignature($request, false))->toBeFalse();
});

test('signed url parameter cannot be named signature', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        $request = Request::create('http://www.foo.com/')
    );
    $url->setKeyResolver(function () {
        return 'secret';
    });

    $route = new Route(['GET'], 'foo/{signature}', ['as' => 'foo', function () {
        //
    }]);
    $routes->add($route);

    Request::create($url->signedRoute('foo', ['signature' => 'bar']));
})->throws(InvalidArgumentException::class, 'reserved');

test('signed url parameter cannot be named expires', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        $request = Request::create('http://www.foo.com/')
    );
    $url->setKeyResolver(function () {
        return 'secret';
    });

    $route = new Route(['GET'], 'foo/{expires}', ['as' => 'foo', function () {
        //
    }]);
    $routes->add($route);

    Request::create($url->signedRoute('foo', ['expires' => 253402300799]));
})->throws(InvalidArgumentException::class, 'reserved');

test('route generation with backed enums', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $namedRoute = new Route(['GET'], '/foo/{bar}', ['as' => 'foo.bar']);
    $routes->add($namedRoute);

    expect($url->route('foo.bar', CategoryBackedEnum::Fruits))->toBe('http://www.foo.com/foo/fruits');
});

test('route generation with nested backed enums', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $namedRoute = new Route(['GET'], '/foo', ['as' => 'foo']);
    $routes->add($namedRoute);

    expect($url->route('foo', ['filter' => [CategoryBackedEnum::People, CategoryBackedEnum::Fruits]]))->toBe('http://www.foo.com/foo?filter%5B0%5D=people&filter%5B1%5D=fruits');
});

test('route generation with unit enums', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $namedRoute = new Route(['GET'], '/foo/{bar}', ['as' => 'foo.bar']);
    $routes->add($namedRoute);

    expect($url->route('foo.bar', CategoryEnum::Fruits))->toBe('http://www.foo.com/foo/Fruits');
});

test('route generation with nested unit enums', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $namedRoute = new Route(['GET'], '/foo', ['as' => 'foo']);
    $routes->add($namedRoute);

    expect($url->route('foo', ['filter' => [CategoryEnum::People, CategoryEnum::Fruits]]))->toBe('http://www.foo.com/foo?filter%5B0%5D=People&filter%5B1%5D=Fruits');
});

test('signed url with key resolver', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        $request = Request::create('http://www.foo.com/')
    );
    $url->setKeyResolver(function () {
        return 'first-secret';
    });

    $route = new Route(['GET'], 'foo', ['as' => 'foo', function () {
        //
    }]);
    $routes->add($route);

    $firstRequest = Request::create($url->signedRoute('foo'));

    expect($url->hasValidSignature($firstRequest))->toBeTrue();

    $request = Request::create($url->signedRoute('foo').'?tampered=true');

    expect($url->hasValidSignature($request))->toBeFalse();

    $url2 = $url->withKeyResolver(function () {
        return 'second-secret';
    });

    expect($url2->hasValidSignature($firstRequest))->toBeFalse();

    $secondRequest = Request::create($url2->signedRoute('foo'));

    expect($url2->hasValidSignature($secondRequest))->toBeTrue();
    expect($url->hasValidSignature($secondRequest))->toBeFalse();

    // Key resolver also supports multiple keys, for app key rotation via the config "app.previous_keys"
    $url3 = $url->withKeyResolver(function () {
        return ['first-secret', 'second-secret'];
    });

    expect($url3->hasValidSignature($firstRequest))->toBeTrue();
    expect($url3->hasValidSignature($secondRequest))->toBeTrue();
});

test('signed url with array signature returns false without warning', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('http://www.foo.com/')
    );
    $url->setKeyResolver(function () {
        return 'secret';
    });

    $route = new Route(['GET'], 'foo', ['as' => 'foo', function () {
        //
    }]);
    $routes->add($route);

    // ?signature[]=foo&signature[]=bar previously raised an
    // "Array to string conversion" warning.
    $request = Request::create('http://www.foo.com/foo?signature[]=foo&signature[]=bar');

    set_error_handler(static function (int $errno, string $errstr) {
        throw new \ErrorException($errstr, 0, $errno);
    }, E_WARNING);

    try {
        expect($url->hasValidSignature($request))->toBeFalse();
    } finally {
        restore_error_handler();
    }
});

test('signed url with array expires returns false', function () {
    $url = new UrlGenerator(
        new RouteCollection,
        Request::create('http://www.foo.com/')
    );
    $url->setKeyResolver(function () {
        return 'secret';
    });

    // ?expires[]=99999999999 is truthy but comparing timestamp > array is always
    // false in PHP, so without the guard the URL would never appear expired.
    $request = Request::create('http://www.foo.com/foo?expires[]=99999999999');

    expect($url->signatureHasNotExpired($request))->toBeFalse();
});

test('missing named route resolution', function () {
    $url = new UrlGenerator(
        new RouteCollection,
        Request::create('http://www.foo.com/')
    );

    $url->resolveMissingNamedRoutesUsing(fn ($name, $parameters, $absolute) => 'test-url');

    expect($url->route('foo'))->toBe('test-url');
});

test('passed parameters have precedence over defaults', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('https://www.foo.com/')
    );

    $url->defaults([
        'tenant' => 'defaultTenant',
    ]);

    $route = new Route(['GET'], 'bar/{tenant}/{post}', ['as' => 'bar', fn () => '']);
    $routes->add($route);

    // Named parameters
    expect($url->route('bar', [
        'tenant' => tap(new RoutableInterfaceStub, fn ($x) => $x->key = 'concreteTenant'),
        'post' => tap(new RoutableInterfaceStub, fn ($x) => $x->key = 'concretePost'),
    ]))->toBe('https://www.foo.com/bar/concreteTenant/concretePost');

    // Positional parameters
    expect($url->route('bar', [
        tap(new RoutableInterfaceStub, fn ($x) => $x->key = 'concreteTenant'),
        tap(new RoutableInterfaceStub, fn ($x) => $x->key = 'concretePost'),
    ]))->toBe('https://www.foo.com/bar/concreteTenant/concretePost');
});

test('complex route generation with defaults and binding fields', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('https://www.foo.com/')
    );

    $url->defaults([
        'tenant' => 'defaultTenant',
        'tenant:slug' => 'defaultTenantSlug',
        'team' => 'defaultTeam',
        'team:slug' => 'defaultTeamSlug',
        'user' => 'defaultUser',
        'user:slug' => 'defaultUserSlug',
    ]);

    $keyParam = fn ($value) => tap(new RoutableInterfaceStub, fn ($routable) => $routable->key = $value);
    $slugParam = fn ($value) => tap(new RoutableInterfaceStub, fn ($routable) => $routable->slug = $value);

    /**
     * One parameter with a default value, one without a default value.
     *
     * No binding fields.
     */
    $route = new Route(['GET'], 'tenantPost/{tenant}/{post}', ['as' => 'tenantPost', fn () => '']);
    $routes->add($route);

    // tenantPost: Both parameters passed positionally
    expect($url->route('tenantPost', [$keyParam('concreteTenant'), $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantPost/concreteTenant/concretePost');

    // tenantPost: Both parameters passed with keys
    expect($url->route('tenantPost', ['tenant' => $keyParam('concreteTenant'), 'post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantPost/concreteTenant/concretePost');

    // tenantPost: Tenant (with default) omitted, post passed positionally
    expect($url->route('tenantPost', [$keyParam('concretePost')]))->toBe('https://www.foo.com/tenantPost/defaultTenant/concretePost');

    // tenantPost: Tenant (with default) omitted, post passed using key
    expect($url->route('tenantPost', ['post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantPost/defaultTenant/concretePost');

    /**
     * One parameter with a default value, one without a default value.
     *
     * Binding field for the first {tenant} parameter with a default value.
     */
    $route = new Route(['GET'], 'tenantSlugPost/{tenant:slug}/{post}', ['as' => 'tenantSlugPost', fn () => '']);
    $routes->add($route);

    // tenantSlugPost: Both parameters passed positionally
    expect($url->route('tenantSlugPost', [$slugParam('concreteTenantSlug'), $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugPost/concreteTenantSlug/concretePost');

    // tenantSlugPost: Both parameters passed with keys
    expect($url->route('tenantSlugPost', ['tenant' => $slugParam('concreteTenantSlug'), 'post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugPost/concreteTenantSlug/concretePost');

    // tenantSlugPost: Tenant (with default) omitted, post passed positionally
    expect($url->route('tenantSlugPost', [$keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugPost/defaultTenantSlug/concretePost');

    // tenantSlugPost: Tenant (with default) omitted, post passed using key
    expect($url->route('tenantSlugPost', ['post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugPost/defaultTenantSlug/concretePost');

    // Repeat the two assertions above without the 'tenant' default (without slug)
    $url->defaults(['tenant' => null]);

    // tenantSlugPost: Tenant (with default) omitted, post passed positionally, with the default value for 'tenant' (without slug) removed
    expect($url->route('tenantSlugPost', [$keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugPost/defaultTenantSlug/concretePost');

    // tenantSlugPost: Tenant (with default) omitted, post passed using key, with the default value for 'tenant' (without slug) removed
    expect($url->route('tenantSlugPost', ['post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugPost/defaultTenantSlug/concretePost');

    // Revert the default value for the tenant parameter back
    $url->defaults(['tenant' => 'defaultTenant']);

    /**
     * One parameter with a default value, one without a default value.
     *
     * Binding field for the second parameter without a default value.
     *
     * This is the only route in this test where we use a binding field
     * for a parameter that does not have a default value and is not
     * the first parameter. This is the simplest scenario so it doesn't
     * need to be tested as repetitively as the other scenarios which are
     * all special in some way.
     */
    $route = new Route(['GET'], 'tenantPostSlug/{tenant}/{post:slug}', ['as' => 'tenantPostSlug', fn () => '']);
    $routes->add($route);

    // tenantPostSlug: Both parameters passed positionally
    expect($url->route('tenantPostSlug', [$keyParam('concreteTenant'), $slugParam('concretePostSlug')]))->toBe('https://www.foo.com/tenantPostSlug/concreteTenant/concretePostSlug');

    // tenantPostSlug: Both parameters passed with keys
    expect($url->route('tenantPostSlug', ['tenant' => $keyParam('concreteTenant'), 'post' => $slugParam('concretePostSlug')]))->toBe('https://www.foo.com/tenantPostSlug/concreteTenant/concretePostSlug');

    // tenantPostSlug: Tenant (with default) omitted, post passed positionally
    expect($url->route('tenantPostSlug', [$slugParam('concretePostSlug')]))->toBe('https://www.foo.com/tenantPostSlug/defaultTenant/concretePostSlug');

    // tenantPostSlug: Tenant (with default) omitted, post passed using key
    expect($url->route('tenantPostSlug', ['post' => $slugParam('concretePostSlug')]))->toBe('https://www.foo.com/tenantPostSlug/defaultTenant/concretePostSlug');

    /**
     * Two parameters with a default value, one without.
     *
     * Having established that passing parameters by key works fine above,
     * we mainly test positional parameters in variations of this route.
     */
    $route = new Route(['GET'], 'tenantTeamPost/{tenant}/{team}/{post}', ['as' => 'tenantTeamPost', fn () => '']);
    $routes->add($route);

    // tenantTeamPost: All parameters passed positionally
    expect($url->route('tenantTeamPost', [$keyParam('concreteTenant'), $keyParam('concreteTeam'), $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantTeamPost/concreteTenant/concreteTeam/concretePost');

    // tenantTeamPost: Tenant (with default) omitted, team and post passed positionally
    expect($url->route('tenantTeamPost', [$keyParam('concreteTeam'), $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantTeamPost/defaultTenant/concreteTeam/concretePost');

    // tenantTeamPost: Tenant and team (with defaults) omitted, post passed positionally
    expect($url->route('tenantTeamPost', [$keyParam('concretePost')]))->toBe('https://www.foo.com/tenantTeamPost/defaultTenant/defaultTeam/concretePost');

    // tenantTeamPost: Tenant passed by key, team (with default) omitted, post passed positionally
    expect($url->route('tenantTeamPost', ['tenant' => $keyParam('concreteTenant'), $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantTeamPost/concreteTenant/defaultTeam/concretePost');

    /**
     * Two parameters with a default value, one without.
     *
     * The first {tenant} parameter also has a binding field.
     */
    $route = new Route(['GET'], 'tenantSlugTeamPost/{tenant:slug}/{team}/{post}', ['as' => 'tenantSlugTeamPost', fn () => '']);
    $routes->add($route);

    // tenantSlugTeamPost: All parameters passed positionally
    expect($url->route('tenantSlugTeamPost', [$slugParam('concreteTenantSlug'), $keyParam('concreteTeam'), $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugTeamPost/concreteTenantSlug/concreteTeam/concretePost');

    // tenantSlugTeamPost: Tenant (with default) omitted, team and post passed positionally
    expect($url->route('tenantSlugTeamPost', [$keyParam('concreteTeam'), $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugTeamPost/defaultTenantSlug/concreteTeam/concretePost');

    // tenantSlugTeamPost: Tenant and team (with defaults) omitted, post passed positionally
    expect($url->route('tenantSlugTeamPost', [$keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugTeamPost/defaultTenantSlug/defaultTeam/concretePost');

    // tenantSlugTeamPost: Tenant passed by key, team (with default) omitted, post passed positionally
    expect($url->route('tenantSlugTeamPost', ['tenant' => $slugParam('concreteTenantSlug'), $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugTeamPost/concreteTenantSlug/defaultTeam/concretePost');

    /**
     * Two parameters with a default value, one without.
     *
     * The second {team} parameter also has a binding field.
     */
    $route = new Route(['GET'], 'tenantTeamSlugPost/{tenant}/{team:slug}/{post}', ['as' => 'tenantTeamSlugPost', fn () => '']);
    $routes->add($route);

    // tenantTeamSlugPost: All parameters passed positionally
    expect($url->route('tenantTeamSlugPost', [$keyParam('concreteTenant'), $slugParam('concreteTeamSlug'), $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantTeamSlugPost/concreteTenant/concreteTeamSlug/concretePost');

    // tenantTeamSlugPost: Tenant (with default) omitted, team and post passed positionally
    expect($url->route('tenantTeamSlugPost', [$slugParam('concreteTeamSlug'), $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantTeamSlugPost/defaultTenant/concreteTeamSlug/concretePost');

    // tenantTeamSlugPost: Tenant and team (with defaults) omitted, post passed positionally
    expect($url->route('tenantTeamSlugPost', [$keyParam('concretePost')]))->toBe('https://www.foo.com/tenantTeamSlugPost/defaultTenant/defaultTeamSlug/concretePost');

    // tenantTeamSlugPost: Tenant passed by key, team (with default) omitted, post passed positionally
    expect($url->route('tenantTeamSlugPost', ['tenant' => $keyParam('concreteTenantSlug'), $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantTeamSlugPost/concreteTenantSlug/defaultTeamSlug/concretePost');

    /**
     * Two parameters with a default value, one without.
     *
     * Both parameters with default values, {tenant} and {team}, also have binding fields.
     */
    $route = new Route(['GET'], 'tenantSlugTeamSlugPost/{tenant:slug}/{team:slug}/{post}', ['as' => 'tenantSlugTeamSlugPost', fn () => '']);
    $routes->add($route);

    // tenantSlugTeamSlugPost: All parameters passed positionally
    expect($url->route('tenantSlugTeamSlugPost', [$slugParam('concreteTenantSlug'), $slugParam('concreteTeamSlug'), $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugTeamSlugPost/concreteTenantSlug/concreteTeamSlug/concretePost');

    // tenantSlugTeamSlugPost: Tenant (with default) omitted, team and post passed positionally
    expect($url->route('tenantSlugTeamSlugPost', [$slugParam('concreteTeamSlug'), $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugTeamSlugPost/defaultTenantSlug/concreteTeamSlug/concretePost');

    // tenantSlugTeamSlugPost: Tenant and team (with defaults) omitted, post passed positionally
    expect($url->route('tenantSlugTeamSlugPost', [$keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugTeamSlugPost/defaultTenantSlug/defaultTeamSlug/concretePost');

    // tenantSlugTeamSlugPost: Tenant passed by key, team (with default) omitted, post passed positionally
    expect($url->route('tenantSlugTeamSlugPost', ['tenant' => $slugParam('concreteTenantSlug'), $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugTeamSlugPost/concreteTenantSlug/defaultTeamSlug/concretePost');

    /**
     * One parameter without a default value, one with a default value.
     *
     * Importantly, the parameter with the default value comes second.
     */
    $route = new Route(['GET'], 'postUser/{post}/{user}', ['as' => 'postUser', fn () => '']);
    $routes->add($route);

    // postUser: Both parameters passed positionally
    expect($url->route('postUser', [$keyParam('concretePost'), $keyParam('concreteUser')]))->toBe('https://www.foo.com/postUser/concretePost/concreteUser');

    // postUser: Both parameters passed with keys
    expect($url->route('postUser', ['user' => $keyParam('concreteUser'), 'post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/postUser/concretePost/concreteUser');

    // postUser: User (with default) omitted, post passed positionally
    expect($url->route('postUser', [$keyParam('concretePost')]))->toBe('https://www.foo.com/postUser/concretePost/defaultUser');

    // postUser: User (with default) omitted, post passed using key
    expect($url->route('postUser', ['post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/postUser/concretePost/defaultUser');

    /**
     * One parameter without a default value, one with a default value.
     *
     * Importantly, the parameter with the default value comes second.
     *
     * In this variation the first parameter, without a default value,
     * also has a binding field.
     */
    $route = new Route(['GET'], 'postSlugUser/{post:slug}/{user}', ['as' => 'postSlugUser', fn () => '']);
    $routes->add($route);

    // postSlugUser: Both parameters passed positionally
    expect($url->route('postSlugUser', [$slugParam('concretePostSlug'), $keyParam('concreteUser')]))->toBe('https://www.foo.com/postSlugUser/concretePostSlug/concreteUser');

    // postSlugUser: Both parameters passed with keys
    expect($url->route('postSlugUser', ['post' => $slugParam('concretePostSlug'), 'user' => $keyParam('concreteUser')]))->toBe('https://www.foo.com/postSlugUser/concretePostSlug/concreteUser');

    // postSlugUser: User (with default) omitted, post passed positionally
    expect($url->route('postSlugUser', [$slugParam('concretePostSlug')]))->toBe('https://www.foo.com/postSlugUser/concretePostSlug/defaultUser');

    // postSlugUser: User (with default) omitted, post passed using key
    expect($url->route('postSlugUser', ['post' => $slugParam('concretePostSlug')]))->toBe('https://www.foo.com/postSlugUser/concretePostSlug/defaultUser');

    /**
     * One parameter without a default value, one with a default value.
     *
     * Importantly, the parameter with the default value comes second.
     *
     * In this variation the second parameter, with a default value,
     * also has a binding field.
     */
    $route = new Route(['GET'], 'postUserSlug/{post}/{user:slug}', ['as' => 'postUserSlug', fn () => '']);
    $routes->add($route);

    // postUserSlug: Both parameters passed positionally
    expect($url->route('postUserSlug', [$keyParam('concretePost'), $slugParam('concreteUserSlug')]))->toBe('https://www.foo.com/postUserSlug/concretePost/concreteUserSlug');

    // postUserSlug: Both parameters passed with keys
    expect($url->route('postUserSlug', ['post' => $keyParam('concretePost'), 'user' => $slugParam('concreteUserSlug')]))->toBe('https://www.foo.com/postUserSlug/concretePost/concreteUserSlug');

    // postUserSlug: User (with default) omitted, post passed positionally
    expect($url->route('postUserSlug', [$keyParam('concretePost')]))->toBe('https://www.foo.com/postUserSlug/concretePost/defaultUserSlug');

    // postUserSlug: User (with default) omitted, post passed using key
    expect($url->route('postUserSlug', ['post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/postUserSlug/concretePost/defaultUserSlug');

    /**
     * One parameter without a default value, one with a default value.
     *
     * Importantly, the parameter with the default value comes second.
     *
     * In this variation, both parameters have binding fields.
     */
    $route = new Route(['GET'], 'postSlugUserSlug/{post:slug}/{user:slug}', ['as' => 'postSlugUserSlug', fn () => '']);
    $routes->add($route);

    // postSlugUserSlug: Both parameters passed positionally
    expect($url->route('postSlugUserSlug', [$slugParam('concretePostSlug'), $slugParam('concreteUserSlug')]))->toBe('https://www.foo.com/postSlugUserSlug/concretePostSlug/concreteUserSlug');

    // postSlugUserSlug: Both parameters passed with keys
    expect($url->route('postSlugUserSlug', ['post' => $slugParam('concretePostSlug'), 'user' => $slugParam('concreteUserSlug')]))->toBe('https://www.foo.com/postSlugUserSlug/concretePostSlug/concreteUserSlug');

    // postSlugUserSlug: User (with default) omitted, post passed positionally
    expect($url->route('postSlugUserSlug', [$slugParam('concretePostSlug')]))->toBe('https://www.foo.com/postSlugUserSlug/concretePostSlug/defaultUserSlug');

    // postSlugUserSlug: User (with default) omitted, post passed using key
    expect($url->route('postSlugUserSlug', ['post' => $slugParam('concretePostSlug')]))->toBe('https://www.foo.com/postSlugUserSlug/concretePostSlug/defaultUserSlug');

    /**
     * Parameter without a default value in between two parameters with default values.
     */
    $route = new Route(['GET'], 'tenantPostUser/{tenant}/{post}/{user}', ['as' => 'tenantPostUser', fn () => '']);
    $routes->add($route);

    // tenantPostUser: All parameters passed positionally
    expect($url->route('tenantPostUser', [$keyParam('concreteTenant'), $keyParam('concretePost'), $keyParam('concreteUser')]))->toBe('https://www.foo.com/tenantPostUser/concreteTenant/concretePost/concreteUser');

    // tenantPostUser: Tenant parameter omitted, post and user passed positionally
    expect($url->route('tenantPostUser', [$keyParam('concretePost'), $keyParam('concreteUser')]))->toBe('https://www.foo.com/tenantPostUser/defaultTenant/concretePost/concreteUser');

    // tenantPostUser: Both tenant and user (with defaults) omitted, post passed positionally
    expect($url->route('tenantPostUser', [$keyParam('concretePost')]))->toBe('https://www.foo.com/tenantPostUser/defaultTenant/concretePost/defaultUser');

    // tenantPostUser: All parameters passed with keys
    expect($url->route('tenantPostUser', ['tenant' => $keyParam('concreteTenant'), 'post' => $keyParam('concretePost'), 'user' => $keyParam('concreteUser')]))->toBe('https://www.foo.com/tenantPostUser/concreteTenant/concretePost/concreteUser');

    // tenantPostUser: Both tenant and user (with defaults) omitted, post passed using key
    expect($url->route('tenantPostUser', ['post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantPostUser/defaultTenant/concretePost/defaultUser');

    // tenantPostUser: Tenant parameter (with default) omitted, post and user passed using key
    expect($url->route('tenantPostUser', ['post' => $keyParam('concretePost'), 'user' => $keyParam('concreteUser')]))->toBe('https://www.foo.com/tenantPostUser/defaultTenant/concretePost/concreteUser');

    // tenantPostUser: User parameter (with default) omitted, tenant and post passed using key
    expect($url->route('tenantPostUser', ['tenant' => $keyParam('concreteTenant'), 'post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantPostUser/concreteTenant/concretePost/defaultUser');

    /**
     * Parameter without a default value in between two parameters with a default value.
     *
     * In this variation of this route, the first {tenant} parameter, with a default value,
     * also has a binding field.
     */
    $route = new Route(['GET'], 'tenantSlugPostUser/{tenant:slug}/{post}/{user}', ['as' => 'tenantSlugPostUser', fn () => '']);
    $routes->add($route);

    // tenantSlugPostUser: All parameters passed positionally
    expect($url->route('tenantSlugPostUser', [$slugParam('concreteTenantSlug'), $keyParam('concretePost'), $keyParam('concreteUser')]))->toBe('https://www.foo.com/tenantSlugPostUser/concreteTenantSlug/concretePost/concreteUser');

    // tenantSlugPostUser: Tenant parameter omitted, post and user passed positionally
    expect($url->route('tenantSlugPostUser', [$keyParam('concretePost'), $keyParam('concreteUser')]))->toBe('https://www.foo.com/tenantSlugPostUser/defaultTenantSlug/concretePost/concreteUser');

    // tenantSlugPostUser: Both tenant and user (with defaults) omitted, post passed positionally
    expect($url->route('tenantSlugPostUser', [$keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugPostUser/defaultTenantSlug/concretePost/defaultUser');

    // tenantSlugPostUser: All parameters passed with keys
    expect($url->route('tenantSlugPostUser', ['tenant' => $slugParam('concreteTenantSlug'), 'post' => $keyParam('concretePost'), 'user' => $keyParam('concreteUser')]))->toBe('https://www.foo.com/tenantSlugPostUser/concreteTenantSlug/concretePost/concreteUser');

    // tenantSlugPostUser: Both tenant and user (with defaults) omitted, post passed using key
    expect($url->route('tenantSlugPostUser', ['post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugPostUser/defaultTenantSlug/concretePost/defaultUser');

    // tenantSlugPostUser: Tenant parameter (with default) omitted, post and user passed using key
    expect($url->route('tenantSlugPostUser', ['post' => $keyParam('concretePost'), 'user' => $keyParam('concreteUser')]))->toBe('https://www.foo.com/tenantSlugPostUser/defaultTenantSlug/concretePost/concreteUser');

    // tenantSlugPostUser: User parameter (with default) omitted, tenant and post passed using key
    expect($url->route('tenantSlugPostUser', ['tenant' => $slugParam('concreteTenantSlug'), 'post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugPostUser/concreteTenantSlug/concretePost/defaultUser');

    /**
     * Parameter without a default value in between two parameters with a default value.
     *
     * In this variation of this route, the last {user} parameter, with a default value,
     * also has a binding field.
     */
    $route = new Route(['GET'], 'tenantPostUserSlug/{tenant}/{post}/{user:slug}', ['as' => 'tenantPostUserSlug', fn () => '']);
    $routes->add($route);

    // tenantPostUserSlug: All parameters passed positionally
    expect($url->route('tenantPostUserSlug', [$keyParam('concreteTenant'), $keyParam('concretePost'), $slugParam('concreteUserSlug')]))->toBe('https://www.foo.com/tenantPostUserSlug/concreteTenant/concretePost/concreteUserSlug');

    // tenantPostUserSlug: Tenant parameter omitted, post and user passed positionally
    expect($url->route('tenantPostUserSlug', [$keyParam('concretePost'), $slugParam('concreteUserSlug')]))->toBe('https://www.foo.com/tenantPostUserSlug/defaultTenant/concretePost/concreteUserSlug');

    // tenantPostUserSlug: Both tenant and user (with defaults) omitted, post passed positionally
    expect($url->route('tenantPostUserSlug', [$keyParam('concretePost')]))->toBe('https://www.foo.com/tenantPostUserSlug/defaultTenant/concretePost/defaultUserSlug');

    // tenantPostUserSlug: All parameters passed with keys
    expect($url->route('tenantPostUserSlug', ['tenant' => $keyParam('concreteTenant'), 'post' => $keyParam('concretePost'), 'user' => $slugParam('concreteUserSlug')]))->toBe('https://www.foo.com/tenantPostUserSlug/concreteTenant/concretePost/concreteUserSlug');

    // tenantPostUserSlug: Both tenant and user (with defaults) omitted, post passed using key
    expect($url->route('tenantPostUserSlug', ['post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantPostUserSlug/defaultTenant/concretePost/defaultUserSlug');

    // tenantPostUserSlug: Tenant parameter (with default) omitted, post and user passed using key
    expect($url->route('tenantPostUserSlug', ['post' => $keyParam('concretePost'), 'user' => $slugParam('concreteUserSlug')]))->toBe('https://www.foo.com/tenantPostUserSlug/defaultTenant/concretePost/concreteUserSlug');

    // tenantPostUserSlug: User parameter (with default) omitted, tenant and post passed using key
    expect($url->route('tenantPostUserSlug', ['tenant' => $keyParam('concreteTenant'), 'post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantPostUserSlug/concreteTenant/concretePost/defaultUserSlug');

    /**
     * Parameter without a default value in between two parameters with a default value.
     *
     * In this variation of this route, the first {tenant} parameter, with a default value,
     * also has a binding field.
     */
    $route = new Route(['GET'], 'tenantSlugPostUser/{tenant:slug}/{post}/{user}', ['as' => 'tenantSlugPostUser', fn () => '']);
    $routes->add($route);

    // tenantSlugPostUser: All parameters passed positionally
    expect($url->route('tenantSlugPostUser', [$slugParam('concreteTenantSlug'), $keyParam('concretePost'), $keyParam('concreteUser')]))->toBe('https://www.foo.com/tenantSlugPostUser/concreteTenantSlug/concretePost/concreteUser');

    // tenantSlugPostUser: Tenant parameter omitted, post and user passed positionally
    expect($url->route('tenantSlugPostUser', [$keyParam('concretePost'), $keyParam('concreteUser')]))->toBe('https://www.foo.com/tenantSlugPostUser/defaultTenantSlug/concretePost/concreteUser');

    // tenantSlugPostUser: Both tenant and user (with defaults) omitted, post passed positionally
    expect($url->route('tenantSlugPostUser', [$keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugPostUser/defaultTenantSlug/concretePost/defaultUser');

    // tenantSlugPostUser: All parameters passed with keys
    expect($url->route('tenantSlugPostUser', ['tenant' => $slugParam('concreteTenantSlug'), 'post' => $keyParam('concretePost'), 'user' => $keyParam('concreteUser')]))->toBe('https://www.foo.com/tenantSlugPostUser/concreteTenantSlug/concretePost/concreteUser');

    // tenantSlugPostUser: Both tenant and user (with defaults) omitted, post passed using key
    expect($url->route('tenantSlugPostUser', ['post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugPostUser/defaultTenantSlug/concretePost/defaultUser');

    // tenantSlugPostUser: Tenant parameter (with default) omitted, post and user passed using key
    expect($url->route('tenantSlugPostUser', ['post' => $keyParam('concretePost'), 'user' => $keyParam('concreteUser')]))->toBe('https://www.foo.com/tenantSlugPostUser/defaultTenantSlug/concretePost/concreteUser');

    // tenantSlugPostUser: User parameter (with default) omitted, tenant and post passed using key
    expect($url->route('tenantSlugPostUser', ['tenant' => $slugParam('concreteTenantSlug'), 'post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugPostUser/concreteTenantSlug/concretePost/defaultUser');

    /**
     * Parameter without a default value in between two parameters with a default value.
     *
     * In this variation of this route, both fields with a default value, {tenant} and
     * {user}, also have binding fields.
     */
    $route = new Route(['GET'], 'tenantSlugPostUserSlug/{tenant:slug}/{post}/{user:slug}', ['as' => 'tenantSlugPostUserSlug', fn () => '']);
    $routes->add($route);

    // tenantSlugPostUserSlug: All parameters passed positionally
    expect($url->route('tenantSlugPostUserSlug', [$slugParam('concreteTenantSlug'), $keyParam('concretePost'), $slugParam('concreteUserSlug')]))->toBe('https://www.foo.com/tenantSlugPostUserSlug/concreteTenantSlug/concretePost/concreteUserSlug');

    // tenantSlugPostUserSlug: Tenant parameter omitted, post and user passed positionally
    expect($url->route('tenantSlugPostUserSlug', [$keyParam('concretePost'), $slugParam('concreteUserSlug')]))->toBe('https://www.foo.com/tenantSlugPostUserSlug/defaultTenantSlug/concretePost/concreteUserSlug');

    // tenantSlugPostUserSlug: Both tenant and user (with defaults) omitted, post passed positionally
    expect($url->route('tenantSlugPostUserSlug', [$keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugPostUserSlug/defaultTenantSlug/concretePost/defaultUserSlug');

    // tenantSlugPostUserSlug: All parameters passed with keys
    expect($url->route('tenantSlugPostUserSlug', ['tenant' => $slugParam('concreteTenantSlug'), 'post' => $keyParam('concretePost'), 'user' => $slugParam('concreteUserSlug')]))->toBe('https://www.foo.com/tenantSlugPostUserSlug/concreteTenantSlug/concretePost/concreteUserSlug');

    // tenantSlugPostUserSlug: Both tenant and user (with defaults) omitted, post passed using key
    expect($url->route('tenantSlugPostUserSlug', ['post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugPostUserSlug/defaultTenantSlug/concretePost/defaultUserSlug');

    // tenantSlugPostUserSlug: Tenant parameter (with default) omitted, post and user passed using key
    expect($url->route('tenantSlugPostUserSlug', ['post' => $keyParam('concretePost'), 'user' => $slugParam('concreteUserSlug')]))->toBe('https://www.foo.com/tenantSlugPostUserSlug/defaultTenantSlug/concretePost/concreteUserSlug');

    // tenantSlugPostUserSlug: User parameter (with default) omitted, tenant and post passed using key
    expect($url->route('tenantSlugPostUserSlug', ['tenant' => $slugParam('concreteTenantSlug'), 'post' => $keyParam('concretePost')]))->toBe('https://www.foo.com/tenantSlugPostUserSlug/concreteTenantSlug/concretePost/defaultUserSlug');
});

test('complex route generation with defaults and mixed parameter syntax', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('https://www.foo.com/')
    );

    $url->defaults([
        'tenant' => 'defaultTenant',
        'user' => 'defaultUser',
    ]);

    /**
     * Parameter without a default value in between two parameters with default values.
     */
    $route = new Route(['GET'], 'tenantPostUser/{tenant}/{post}/{user}', ['as' => 'tenantPostUser', fn () => '']);
    $routes->add($route);

    // If the required post parameter is specified using a key,
    // the positional parameter is used for the user parameter.
    expect($url->route('tenantPostUser', ['post' => 'concretePost', 'concreteUser']))->toBe('https://www.foo.com/tenantPostUser/defaultTenant/concretePost/concreteUser');

    /**
     * Two parameters without default values in between two parameters with default values.
     */
    $route = new Route(['GET'], 'tenantPostCommentUser/{tenant}/{post}/{comment}/{user}', ['as' => 'tenantPostCommentUser', fn () => '']);
    $routes->add($route);

    // Pass first required parameter using a key, second positionally
    expect($url->route('tenantPostCommentUser', ['post' => 'concretePost', 'concreteComment']))->toBe('https://www.foo.com/tenantPostCommentUser/defaultTenant/concretePost/concreteComment/defaultUser');

    // Pass first required parameter positionally, second using a key
    expect($url->route('tenantPostCommentUser', ['concretePost', 'comment' => 'concreteComment']))->toBe('https://www.foo.com/tenantPostCommentUser/defaultTenant/concretePost/concreteComment/defaultUser');

    // Verify that this is order-independent
    expect($url->route('tenantPostCommentUser', ['comment' => 'concreteComment', 'concretePost']))->toBe('https://www.foo.com/tenantPostCommentUser/defaultTenant/concretePost/concreteComment/defaultUser');

    // Both required params passed with keys, positional parameter goes to the user param
    expect($url->route('tenantPostCommentUser', ['post' => 'concretePost', 'comment' => 'concreteComment', 'concreteUser']))->toBe('https://www.foo.com/tenantPostCommentUser/defaultTenant/concretePost/concreteComment/concreteUser');

    // First required param passed with a key, remaining params go to the last two route params
    expect($url->route('tenantPostCommentUser', ['post' => 'concretePost', 'concreteComment', 'concreteUser']))->toBe('https://www.foo.com/tenantPostCommentUser/defaultTenant/concretePost/concreteComment/concreteUser');

    // Comment parameter passed with a key, remaining params filled (last to last)
    expect($url->route('tenantPostCommentUser', ['concretePost', 'comment' => 'concreteComment', 'concreteUser']))->toBe('https://www.foo.com/tenantPostCommentUser/defaultTenant/concretePost/concreteComment/concreteUser');

    // Verify that this is order-independent
    expect($url->route('tenantPostCommentUser', ['comment' => 'concreteComment', 'concretePost', 'concreteUser']))->toBe('https://www.foo.com/tenantPostCommentUser/defaultTenant/concretePost/concreteComment/concreteUser');

    // Both default parameters passed positionally, required parameters passed with keys
    expect($url->route('tenantPostCommentUser', ['concreteTenant', 'post' => 'concretePost', 'comment' => 'concreteComment', 'concreteUser']))->toBe('https://www.foo.com/tenantPostCommentUser/concreteTenant/concretePost/concreteComment/concreteUser');

    // Verify that the positional parameters may come anywhere in the array
    expect($url->route('tenantPostCommentUser', ['post' => 'concretePost', 'comment' => 'concreteComment', 'concreteTenant', 'concreteUser']))->toBe('https://www.foo.com/tenantPostCommentUser/concreteTenant/concretePost/concreteComment/concreteUser');
});

test('defaults can be combined with extra query parameters', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('https://www.foo.com/')
    );

    $url->defaults([
        'tenant' => 'defaultTenant',
        'tenant:slug' => 'defaultTenantSlug',
        'user' => 'defaultUser',
    ]);

    $slugParam = fn ($value) => tap(new RoutableInterfaceStub, fn ($routable) => $routable->slug = $value);

    /**
     * One parameter with a default value, one parameter without a default value.
     */
    $route = new Route(['GET'], 'tenantPost/{tenant}/{post}', ['as' => 'tenantPost', fn () => '']);
    $routes->add($route);

    // tenantPost: Extra positional parameters without values are interpreted as query strings
    expect($url->route('tenantPost', ['concreteTenant', 'concretePost', 'extraQuery']))->toBe('https://www.foo.com/tenantPost/concreteTenant/concretePost?extraQuery');

    // tenantPost: Query parameters without values go at the end
    expect($url->route('tenantPost', ['concreteTenant', 'concretePost', 'extraQuery', 'extra' => 'query']))->toBe('https://www.foo.com/tenantPost/concreteTenant/concretePost?extra=query&extraQuery');

    // tenantPost: Defaults can be used with *named* query parameters
    expect($url->route('tenantPost', ['concretePost', 'extra' => 'query']))->toBe('https://www.foo.com/tenantPost/defaultTenant/concretePost?extra=query');

    // tenantPost: Named query parameters can be placed anywhere in the parameters array
    expect($url->route('tenantPost', ['concreteTenant', 'extra' => 'query', 'concretePost']))->toBe('https://www.foo.com/tenantPost/concreteTenant/concretePost?extra=query');

    /**
     * One parameter with a default value, one parameter without a default value.
     *
     * The first parameter with a default value, {tenant}, also has a binding field.
     */
    $route = new Route(['GET'], 'tenantSlugPost/{tenant:slug}/{post}', ['as' => 'tenantSlugPost', fn () => '']);
    $routes->add($route);

    // tenantSlugPost: Extra positional parameters without values are interpreted as query strings
    expect($url->route('tenantSlugPost', [$slugParam('concreteTenantSlug'), 'concretePost', 'extraQuery']))->toBe('https://www.foo.com/tenantSlugPost/concreteTenantSlug/concretePost?extraQuery');

    // tenantSlugPost: Query parameters without values go at the end
    expect($url->route('tenantSlugPost', [$slugParam('concreteTenantSlug'), 'concretePost', 'extraQuery', 'extra' => 'query']))->toBe('https://www.foo.com/tenantSlugPost/concreteTenantSlug/concretePost?extra=query&extraQuery');

    // tenantSlugPost: Defaults can be used with *named* query parameters
    expect($url->route('tenantSlugPost', ['concretePost', 'extra' => 'query']))->toBe('https://www.foo.com/tenantSlugPost/defaultTenantSlug/concretePost?extra=query');

    // tenantSlugPost: Named query parameters can be placed anywhere in the parameters array
    expect($url->route('tenantSlugPost', [$slugParam('concreteTenantSlug'), 'extra' => 'query', 'concretePost']))->toBe('https://www.foo.com/tenantSlugPost/concreteTenantSlug/concretePost?extra=query');

    /**
     * Parameter without a default value in between two parameters with default values.
     */
    $route = new Route(['GET'], 'tenantPostUser/{tenant}/{post}/{user}', ['as' => 'tenantPostUser', fn () => '']);
    $routes->add($route);

    // tenantPostUser: Query string parameters may be passed positionally if
    // all route parameters are passed as well, i.e. defaults are not used.
    expect($url->route('tenantPostUser', ['concreteTenant', 'concretePost', 'concreteUser', 'extraQuery']))->toBe('https://www.foo.com/tenantPostUser/concreteTenant/concretePost/concreteUser?extraQuery');

    // tenantPostUser: Query string parameters can be passed as key-value
    // pairs if all route params are passed as well, i.e. no defaults.
    expect($url->route('tenantPostUser', ['concreteTenant', 'concretePost', 'concreteUser', 'extraQuery']))->toBe('https://www.foo.com/tenantPostUser/concreteTenant/concretePost/concreteUser?extraQuery');

    // tenantPostUser: With omitted default parameters, query string parameters
    // can only be specified using key-value pairs. Positional query string
    // parameters would be interpreted as route parameters instead.
    expect($url->route('tenantPostUser', ['concretePost', 'concreteUser', 'extra' => 'query']))->toBe('https://www.foo.com/tenantPostUser/defaultTenant/concretePost/concreteUser?extra=query');

    // tenantPostUser: Use defaults for tenant and user, pass post positionally
    // and add an extra query string parameter as a key-value pair.
    expect($url->route('tenantPostUser', ['concretePost', 'extra' => 'query']))->toBe('https://www.foo.com/tenantPostUser/defaultTenant/concretePost/defaultUser?extra=query');
});

test('url generation with optional parameters', function () {
    $url = new UrlGenerator(
        $routes = new RouteCollection,
        Request::create('https://www.foo.com/')
    );

    $url->defaults([
        'tenant' => 'defaultTenant',
        'user' => 'defaultUser',
    ]);

    /**
     * Route with one required parameter and one optional parameter.
     */
    $route = new Route(['GET'], 'postOptionalMethod/{post}/{method?}', ['as' => 'postOptionalMethod', fn () => '']);
    $routes->add($route);

    expect($url->route('postOptionalMethod', 1))->toBe('https://www.foo.com/postOptionalMethod/1');

    expect($url->route('postOptionalMethod', [1, 2]))->toBe('https://www.foo.com/postOptionalMethod/1/2');

    /**
     * Route with two optional parameters.
     */
    $route = new Route(['GET'], 'optionalPostOptionalMethod/{post}/{method?}', ['as' => 'optionalPostOptionalMethod', fn () => '']);
    $routes->add($route);

    expect($url->route('optionalPostOptionalMethod', 1))->toBe('https://www.foo.com/optionalPostOptionalMethod/1');

    expect($url->route('optionalPostOptionalMethod', [1, 2]))->toBe('https://www.foo.com/optionalPostOptionalMethod/1/2');

    /**
     * Route with one default parameter, one required parameter, and one optional parameter.
     */
    $route = new Route(['GET'], 'tenantPostOptionalMethod/{tenant}/{post}/{method?}', ['as' => 'tenantPostOptionalMethod', fn () => '']);
    $routes->add($route);

    // Passing one parameter
    expect($url->route('tenantPostOptionalMethod', ['concretePost']))->toBe('https://www.foo.com/tenantPostOptionalMethod/defaultTenant/concretePost');

    // Passing two parameters: optional parameter is prioritized over parameter with a default value
    expect($url->route('tenantPostOptionalMethod', ['concretePost', 'concreteMethod']))->toBe('https://www.foo.com/tenantPostOptionalMethod/defaultTenant/concretePost/concreteMethod');

    // Passing all three parameters
    expect($url->route('tenantPostOptionalMethod', ['concreteTenant', 'concretePost', 'concreteMethod']))->toBe('https://www.foo.com/tenantPostOptionalMethod/concreteTenant/concretePost/concreteMethod');

    /**
     * Route with two default parameters, one required parameter, and one optional parameter.
     */
    $route = new Route(['GET'], 'tenantUserPostOptionalMethod/{tenant}/{user}/{post}/{method?}', ['as' => 'tenantUserPostOptionalMethod', fn () => '']);
    $routes->add($route);

    // Passing one parameter
    expect($url->route('tenantUserPostOptionalMethod', ['concretePost']))->toBe('https://www.foo.com/tenantUserPostOptionalMethod/defaultTenant/defaultUser/concretePost');

    // Passing two parameters: optional parameter is prioritized over parameters with default values
    expect($url->route('tenantUserPostOptionalMethod', ['concretePost', 'concreteMethod']))->toBe('https://www.foo.com/tenantUserPostOptionalMethod/defaultTenant/defaultUser/concretePost/concreteMethod');

    // Passing three parameters: only the leftmost parameter with a default value uses its default value
    expect($url->route('tenantUserPostOptionalMethod', ['concreteUser', 'concretePost', 'concreteMethod']))->toBe('https://www.foo.com/tenantUserPostOptionalMethod/defaultTenant/concreteUser/concretePost/concreteMethod');

    // Same as the assertion above, but using some named parameters
    expect($url->route('tenantUserPostOptionalMethod', ['user' => 'concreteUser', 'concretePost', 'concreteMethod']))->toBe('https://www.foo.com/tenantUserPostOptionalMethod/defaultTenant/concreteUser/concretePost/concreteMethod');

    // Also using a named parameter, but this time for the post parameter
    expect($url->route('tenantUserPostOptionalMethod', ['concreteUser', 'post' => 'concretePost', 'concreteMethod']))->toBe('https://www.foo.com/tenantUserPostOptionalMethod/defaultTenant/concreteUser/concretePost/concreteMethod');

    // Also using a named parameter, but this time for the optional method parameter
    expect($url->route('tenantUserPostOptionalMethod', ['concreteUser', 'concretePost', 'method' => 'concreteMethod']))->toBe('https://www.foo.com/tenantUserPostOptionalMethod/defaultTenant/concreteUser/concretePost/concreteMethod');

    // Passing all four parameters
    expect($url->route('tenantUserPostOptionalMethod', ['concreteTenant', 'concreteUser', 'concretePost', 'concreteMethod']))->toBe('https://www.foo.com/tenantUserPostOptionalMethod/concreteTenant/concreteUser/concretePost/concreteMethod');

    /**
     * Route with a default parameter, a required parameter, another default parameter, and finally an optional parameter.
     *
     * This tests interleaved default parameters when combined with optional parameters.
     */
    $route = new Route(['GET'], 'tenantPostUserOptionalMethod/{tenant}/{post}/{user}/{method?}', ['as' => 'tenantPostUserOptionalMethod', fn () => '']);
    $routes->add($route);

    // Passing one parameter
    expect($url->route('tenantPostUserOptionalMethod', ['concretePost']))->toBe('https://www.foo.com/tenantPostUserOptionalMethod/defaultTenant/concretePost/defaultUser');

    // Passing two parameters: optional parameter is prioritized over parameters with default values
    expect($url->route('tenantPostUserOptionalMethod', ['concretePost', 'concreteMethod']))->toBe('https://www.foo.com/tenantPostUserOptionalMethod/defaultTenant/concretePost/defaultUser/concreteMethod');

    // Same as the assertion above, but using some named parameters
    expect($url->route('tenantPostUserOptionalMethod', ['post' => 'concretePost', 'concreteMethod']))->toBe('https://www.foo.com/tenantPostUserOptionalMethod/defaultTenant/concretePost/defaultUser/concreteMethod');

    // Also using a named parameter, but this time for the optional parameter
    expect($url->route('tenantPostUserOptionalMethod', ['concretePost', 'method' => 'concreteMethod']))->toBe('https://www.foo.com/tenantPostUserOptionalMethod/defaultTenant/concretePost/defaultUser/concreteMethod');

    // Passing three parameters: only the leftmost parameter with a default value uses its default value
    expect($url->route('tenantPostUserOptionalMethod', ['concretePost', 'concreteUser', 'concreteMethod']))->toBe('https://www.foo.com/tenantPostUserOptionalMethod/defaultTenant/concretePost/concreteUser/concreteMethod');

    // Passing all four parameters
    expect($url->route('tenantPostUserOptionalMethod', ['concreteTenant', 'concretePost', 'concreteUser', 'concreteMethod']))->toBe('https://www.foo.com/tenantPostUserOptionalMethod/concreteTenant/concretePost/concreteUser/concreteMethod');
});

class RoutableInterfaceStub implements UrlRoutable
{
    public $key;
    public $slug = 'test-slug';

    public function getRouteKey()
    {
        return $this->{$this->getRouteKeyName()};
    }

    public function getRouteKeyName()
    {
        return 'key';
    }

    public function resolveRouteBinding($routeKey, $field = null)
    {
        //
    }

    public function resolveChildRouteBinding($childType, $routeKey, $field = null)
    {
        //
    }
}

class InvokableActionStub
{
    public function __invoke()
    {
        return 'hello';
    }
}

class RoutingUrlGeneratorTestUser extends Model implements UrlRoutable
{
    use BindsToRoutes;

    protected $fillable = ['uuid'];
}
