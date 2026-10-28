<?php

namespace Venusian\Serve\Tests\Http;

use Carbon\CarbonInterval;
use Carbon\Unit;
use Mariner\Http\Request;
use Mariner\Image\Image;
use Mariner\Routing\Route;
use Mariner\Session\NullSessionHandler;
use Mariner\Session\Store;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Voyager\Database\Instrument\Model;
use Voyager\Filesystem\UploadedFile;
use Voyager\NutsAndBolts\Collection;
use Venusian\Serve\Tests\Http\Fixtures\TestEnum;
use Venusian\Serve\Tests\Http\Fixtures\TestEnumBacked;
use Venusian\Serve\Tests\Http\Fixtures\TestIntegerEnumBacked;
use InvalidArgumentException;
use RuntimeException;
use Symfony\Component\HttpFoundation\Exception\SessionNotFoundException;
use Symfony\Component\HttpFoundation\File\UploadedFile as SymfonyUploadedFile;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

require_once __DIR__.'/Fixtures/Enums.php';

test('instance method', function () {
    $request = Request::create('');
    expect($request->instance())->toBe($request);
});

test('method method', function () {
    $request = Request::create('', 'GET');
    expect($request->method())->toBe('GET');

    $request = Request::create('', 'HEAD');
    expect($request->method())->toBe('HEAD');

    $request = Request::create('', 'POST');
    expect($request->method())->toBe('POST');

    $request = Request::create('', 'PUT');
    expect($request->method())->toBe('PUT');

    $request = Request::create('', 'PATCH');
    expect($request->method())->toBe('PATCH');

    $request = Request::create('', 'DELETE');
    expect($request->method())->toBe('DELETE');

    $request = Request::create('', 'OPTIONS');
    expect($request->method())->toBe('OPTIONS');
});

test('root method', function () {
    $request = Request::create('http://example.com/foo/bar/script.php?test');
    expect($request->root())->toBe('http://example.com');
});

test('path method', function () {
    $request = Request::create('');
    expect($request->path())->toBe('/');

    $request = Request::create('/foo/bar');
    expect($request->path())->toBe('foo/bar');
});

test('decoded path method', function () {
    $request = Request::create('/foo%20bar');
    expect($request->decodedPath())->toBe('foo bar');
});

test('segment method', function ($path, $segment, $expected) {
    $request = Request::create($path);
    expect($request->segment($segment, 'default'))->toEqual($expected);
})->with('segmentProvider');

dataset('segmentProvider', function () {
    return [
        ['', 1, 'default'],
        ['foo/bar//baz', 1, 'foo'],
        ['foo/bar//baz', 2, 'bar'],
        ['foo/bar//baz', 3, 'baz'],
    ];
});

test('segments method', function ($path, $expected) {
    $request = Request::create($path);
    expect($request->segments())->toEqual($expected);

    $request = Request::create('foo/bar');
    expect($request->segments())->toEqual(['foo', 'bar']);
})->with('segmentsProvider');

dataset('segmentsProvider', function () {
    return [
        ['', []],
        ['foo/bar', ['foo', 'bar']],
        ['foo/bar//baz', ['foo', 'bar', 'baz']],
        ['foo/0/bar', ['foo', '0', 'bar']],
    ];
});

test('url method', function () {
    $request = Request::create('http://foo.com/foo/bar?name=taylor');
    expect($request->url())->toBe('http://foo.com/foo/bar');

    $request = Request::create('http://foo.com/foo/bar/?');
    expect($request->url())->toBe('http://foo.com/foo/bar');
});

test('full url method', function () {
    $request = Request::create('http://foo.com/foo/bar?name=taylor');
    expect($request->fullUrl())->toBe('http://foo.com/foo/bar?name=taylor');

    $request = Request::create('https://foo.com');
    expect($request->fullUrl())->toBe('https://foo.com');

    $request = Request::create('https://foo.com');
    expect($request->fullUrlWithQuery(['coupon' => 'foo']))->toBe('https://foo.com/?coupon=foo');

    $request = Request::create('https://foo.com?a=b');
    expect($request->fullUrl())->toBe('https://foo.com/?a=b');

    $request = Request::create('https://foo.com?a=b');
    expect($request->fullUrlWithQuery(['coupon' => 'foo']))->toBe('https://foo.com/?a=b&coupon=foo');

    $request = Request::create('https://foo.com?a=b');
    expect($request->fullUrlWithQuery(['a' => 'c']))->toBe('https://foo.com/?a=c');

    $request = Request::create('http://foo.com/foo/bar?name=taylor');
    expect($request->fullUrlWithQuery(['name' => 'taylor']))->toBe('http://foo.com/foo/bar?name=taylor');

    $request = Request::create('http://foo.com/foo/bar/?name=taylor');
    expect($request->fullUrlWithQuery(['name' => 'graham']))->toBe('http://foo.com/foo/bar?name=graham');

    $request = Request::create('https://foo.com');
    expect($request->fullUrlWithQuery(['key' => 'value with spaces']))->toBe('https://foo.com/?key=value%20with%20spaces');
});

test('full url without query method', function () {
    $request = Request::create('http://foo.com/foo/bar?name=taylor&age=30');
    expect($request->fullUrlWithoutQuery('name'))->toBe('http://foo.com/foo/bar?age=30');

    $request = Request::create('http://foo.com/foo/bar?name=taylor&age=30');
    expect($request->fullUrlWithoutQuery(['name']))->toBe('http://foo.com/foo/bar?age=30');

    $request = Request::create('http://foo.com/foo/bar?name=taylor&age=30&city=nyc');
    expect($request->fullUrlWithoutQuery(['name', 'age']))->toBe('http://foo.com/foo/bar?city=nyc');

    $request = Request::create('http://foo.com/foo/bar?name=taylor');
    expect($request->fullUrlWithoutQuery('name'))->toBe('http://foo.com/foo/bar');

    $request = Request::create('http://foo.com/foo/bar');
    expect($request->fullUrlWithoutQuery('name'))->toBe('http://foo.com/foo/bar');

    $request = Request::create('https://foo.com?a=b&c=d');
    expect($request->fullUrlWithoutQuery('a'))->toBe('https://foo.com/?c=d');

    $request = Request::create('https://foo.com/?name=taylor&age=30');
    expect($request->fullUrlWithoutQuery('name'))->toBe('https://foo.com/?age=30');
});

test('is method', function () {
    $request = Request::create('/foo/bar');

    expect($request->is('foo*'))->toBeTrue();
    expect($request->is('bar*'))->toBeFalse();
    expect($request->is('*bar*'))->toBeTrue();
    expect($request->is('bar*', 'foo*', 'baz'))->toBeTrue();

    $request = Request::create('/');

    expect($request->is('/'))->toBeTrue();
});

test('full url is method', function () {
    $request = Request::create('http://example.com/foo/bar');

    expect($request->fullUrlIs('http://example.com/foo/bar'))->toBeTrue();
    expect($request->fullUrlIs('example.com*'))->toBeFalse();
    expect($request->fullUrlIs('http://*'))->toBeTrue();
    expect($request->fullUrlIs('*foo*'))->toBeTrue();
    expect($request->fullUrlIs('*bar'))->toBeTrue();
    expect($request->fullUrlIs('*'))->toBeTrue();
});

test('route is method', function () {
    $request = Request::create('/foo/bar');

    expect($request->routeIs('foo.bar'))->toBeFalse();

    $request->setRouteResolver(function () use ($request) {
        $route = new Route('GET', '/foo/bar', ['as' => 'foo.bar']);
        $route->bind($request);

        return $route;
    });

    expect($request->routeIs('foo.bar'))->toBeTrue();
    expect($request->routeIs('foo*', '*bar'))->toBeTrue();
    expect($request->routeIs('foo.foo'))->toBeFalse();
});

test('route method', function () {
    $request = Request::create('/foo/bar');

    $request->setRouteResolver(function () use ($request) {
        $route = new Route('GET', '/foo/{required}/{optional?}', []);
        $route->bind($request);

        return $route;
    });

    expect($request->route('required'))->toBe('bar');
    expect($request->route('required', 'default'))->toBe('bar');
    expect($request->route('optional'))->toBeNull();
    expect($request->route('optional', 'default'))->toBe('default');
});

test('ajax method', function () {
    $request = Request::create('/');
    expect($request->ajax())->toBeFalse();
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'], '{}');
    expect($request->ajax())->toBeTrue();
    $request = Request::create('/', 'POST');
    $request->headers->set('X-Requested-With', 'XMLHttpRequest');
    expect($request->ajax())->toBeTrue();
    $request->headers->set('X-Requested-With', '');
    expect($request->ajax())->toBeFalse();
});

test('prefetch method', function () {
    $request = Request::create('/');
    expect($request->prefetch())->toBeFalse();

    $request->server->set('HTTP_X_MOZ', '');
    expect($request->prefetch())->toBeFalse();
    $request->server->set('HTTP_X_MOZ', 'prefetch');
    expect($request->prefetch())->toBeTrue();
    $request->server->set('HTTP_X_MOZ', 'Prefetch');
    expect($request->prefetch())->toBeTrue();

    $request->server->remove('HTTP_X_MOZ');

    $request->headers->set('Purpose', '');
    expect($request->prefetch())->toBeFalse();
    $request->headers->set('Purpose', 'prefetch');
    expect($request->prefetch())->toBeTrue();
    $request->headers->set('Purpose', 'Prefetch');
    expect($request->prefetch())->toBeTrue();

    $request->headers->remove('Purpose');

    $request->headers->set('Sec-Purpose', '');
    expect($request->prefetch())->toBeFalse();
    $request->headers->set('Sec-Purpose', 'prefetch');
    expect($request->prefetch())->toBeTrue();
    $request->headers->set('Sec-Purpose', 'Prefetch');
    expect($request->prefetch())->toBeTrue();
});

test('pjax method', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_X_PJAX' => 'true'], '{}');
    expect($request->pjax())->toBeTrue();
    $request->headers->set('X-PJAX', 'false');
    expect($request->pjax())->toBeTrue();
    $request->headers->set('X-PJAX', null);
    expect($request->pjax())->toBeFalse();
    $request->headers->set('X-PJAX', '');
    expect($request->pjax())->toBeFalse();
});

test('secure method', function () {
    $request = Request::create('http://example.com');
    expect($request->secure())->toBeFalse();
    $request = Request::create('https://example.com');
    expect($request->secure())->toBeTrue();
});

test('user agent method', function () {
    $request = Request::create('/', 'GET', [], [], [], [
        'HTTP_USER_AGENT' => 'Laravel',
    ]);

    expect($request->userAgent())->toBe('Laravel');
});

test('host method', function () {
    $request = Request::create('http://example.com');
    expect($request->host())->toBe('example.com');

    $request = Request::create('https://example.com');
    expect($request->host())->toBe('example.com');

    $request = Request::create('https://example.com:8080');
    expect($request->host())->toBe('example.com');

    $request = Request::create('http://example.com:8080');
    expect($request->host())->toBe('example.com');
});

test('http host method', function () {
    $request = Request::create('http://example.com');
    expect($request->httpHost())->toBe('example.com');

    $request = Request::create('https://example.com');
    expect($request->httpHost())->toBe('example.com');

    $request = Request::create('http://example.com:8080');
    expect($request->httpHost())->toBe('example.com:8080');

    $request = Request::create('https://example.com:8080');
    expect($request->httpHost())->toBe('example.com:8080');
});

test('scheme and http host method', function () {
    $request = Request::create('http://example.com');
    expect($request->schemeAndHttpHost())->toBe('http://example.com');

    $request = Request::create('https://example.com');
    expect($request->schemeAndHttpHost())->toBe('https://example.com');

    $request = Request::create('http://example.com:8080');
    expect($request->schemeAndHttpHost())->toBe('http://example.com:8080');

    $request = Request::create('https://example.com:8080');
    expect($request->schemeAndHttpHost())->toBe('https://example.com:8080');
});

test('has method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'age' => '', 'city' => null]);
    expect($request->has('name'))->toBeTrue();
    expect($request->has('age'))->toBeTrue();
    expect($request->has('city'))->toBeTrue();
    expect($request->has('foo'))->toBeFalse();
    expect($request->has('name', 'email'))->toBeFalse();

    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'email' => 'foo']);
    expect($request->has('name'))->toBeTrue();
    expect($request->has('name', 'email'))->toBeTrue();
    expect($request->has(['name', 'email']))->toBeTrue();

    $request = Request::create('/', 'GET', ['foo' => ['bar', 'bar']]);
    expect($request->has('foo'))->toBeTrue();

    $request = Request::create('/', 'GET', ['foo' => '', 'bar' => null]);
    expect($request->has('foo'))->toBeTrue();
    expect($request->has('bar'))->toBeTrue();

    $request = Request::create('/', 'GET', ['foo' => ['bar' => null, 'baz' => '']]);
    expect($request->has('foo.bar'))->toBeTrue();
    expect($request->has('foo.baz'))->toBeTrue();
});

test('when has method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'age' => '', 'city' => null]);

    $name = $age = $city = $foo = $bar = false;

    $request->whenHas('name', function ($value) use (&$name) {
        $name = $value;
    });

    $request->whenHas('age', function ($value) use (&$age) {
        $age = $value;
    });

    $request->whenHas('city', function ($value) use (&$city) {
        $city = $value;
    });

    $request->whenHas('foo', function () use (&$foo) {
        $foo = 'test';
    });

    $request->whenHas('bar', function () use (&$bar) {
        $bar = 'test';
    }, function () use (&$bar) {
        $bar = true;
    });

    expect($name)->toBe('Taylor');
    expect($age)->toBe('');
    expect($city)->toBeNull();
    expect($foo)->toBeFalse();
    expect($bar)->toBeTrue();
});

test('when filled method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'age' => '', 'city' => null]);

    $name = $age = $city = $foo = $bar = false;

    $request->whenFilled('name', function ($value) use (&$name) {
        $name = $value;
    });

    $request->whenFilled('age', function ($value) use (&$age) {
        $age = 'test';
    });

    $request->whenFilled('city', function ($value) use (&$city) {
        $city = 'test';
    });

    $request->whenFilled('foo', function () use (&$foo) {
        $foo = 'test';
    });

    $request->whenFilled('bar', function () use (&$bar) {
        $bar = 'test';
    }, function () use (&$bar) {
        $bar = true;
    });

    expect($name)->toBe('Taylor');
    expect($age)->toBeFalse();
    expect($city)->toBeFalse();
    expect($foo)->toBeFalse();
    expect($bar)->toBeTrue();
});

test('when enum method', function () {
    $request = Request::create('/', 'GET', ['status' => 'test', 'invalid' => 'invalid', 'empty' => '']);

    $status = $invalid = $empty = $missing = $default = false;

    $request->whenEnum('status', TestEnumBacked::class, function ($value) use (&$status) {
        $status = $value;
    });

    $request->whenEnum('invalid', TestEnumBacked::class, function ($value) use (&$invalid) {
        $invalid = $value;
    });

    $request->whenEnum('empty', TestEnumBacked::class, function ($value) use (&$empty) {
        $empty = $value;
    });

    $request->whenEnum('missing', TestEnumBacked::class, function ($value) use (&$missing) {
        $missing = $value;
    }, function () use (&$default) {
        $default = true;
    });

    expect($status)->toBe(TestEnumBacked::test);
    expect($invalid)->toBeFalse();
    expect($empty)->toBeFalse();
    expect($missing)->toBeFalse();
    expect($default)->toBeTrue();
});

test('missing method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'age' => '', 'city' => null]);
    expect($request->missing('name'))->toBeFalse();
    expect($request->missing('age'))->toBeFalse();
    expect($request->missing('city'))->toBeFalse();
    expect($request->missing('foo'))->toBeTrue();
    expect($request->missing('name', 'email'))->toBeTrue();
    expect($request->missing(['name', 'email']))->toBeTrue();

    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'email' => 'foo']);
    expect($request->missing('name'))->toBeFalse();
    expect($request->missing('name', 'email'))->toBeFalse();

    $request = Request::create('/', 'GET', ['foo' => ['bar', 'bar']]);
    expect($request->missing('foo'))->toBeFalse();

    $request = Request::create('/', 'GET', ['foo' => '', 'bar' => null]);
    expect($request->missing('foo'))->toBeFalse();
    expect($request->missing('bar'))->toBeFalse();

    $request = Request::create('/', 'GET', ['foo' => ['bar' => null, 'baz' => '']]);
    expect($request->missing('foo.bar'))->toBeFalse();
    expect($request->missing('foo.baz'))->toBeFalse();
});

test('when missing method', function () {
    $request = Request::create('/', 'GET', ['bar' => null]);

    $name = $age = $city = $foo = $bar = true;

    $request->whenMissing('name', function ($value) use (&$name) {
        $name = 'Taylor';
    });

    $request->whenMissing('age', function ($value) use (&$age) {
        $age = '';
    });

    $request->whenMissing('city', function ($value) use (&$city) {
        $city = null;
    });

    $request->whenMissing('foo', function () use (&$foo) {
        $foo = false;
    });

    $request->whenMissing('bar', function () use (&$bar) {
        $bar = 'test';
    }, function () use (&$bar) {
        $bar = true;
    });

    expect($name)->toBe('Taylor');
    expect($age)->toBe('');
    expect($city)->toBeNull();
    expect($foo)->toBeFalse();
    expect($bar)->toBeTrue();
});

test('has any method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'age' => '', 'city' => null]);
    expect($request->hasAny('name'))->toBeTrue();
    expect($request->hasAny('age'))->toBeTrue();
    expect($request->hasAny('city'))->toBeTrue();
    expect($request->hasAny('foo'))->toBeFalse();
    expect($request->hasAny('name', 'email'))->toBeTrue();
    expect($request->hasAny(['name', 'email']))->toBeTrue();

    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'email' => 'foo']);
    expect($request->hasAny('name', 'email'))->toBeTrue();
    expect($request->hasAny('surname', 'password'))->toBeFalse();
    expect($request->hasAny(['surname', 'password']))->toBeFalse();

    $request = Request::create('/', 'GET', ['foo' => ['bar' => null, 'baz' => '']]);
    expect($request->hasAny('foo.bar'))->toBeTrue();
    expect($request->hasAny('foo.baz'))->toBeTrue();
    expect($request->hasAny('foo.bax'))->toBeFalse();
    expect($request->hasAny(['foo.bax', 'foo.baz']))->toBeTrue();
});

test('filled method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'age' => '', 'city' => null]);
    expect($request->filled('name'))->toBeTrue();
    expect($request->filled('age'))->toBeFalse();
    expect($request->filled('city'))->toBeFalse();
    expect($request->filled('foo'))->toBeFalse();
    expect($request->filled('name', 'email'))->toBeFalse();

    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'email' => 'foo']);
    expect($request->filled('name'))->toBeTrue();
    expect($request->filled('name', 'email'))->toBeTrue();
    expect($request->filled(['name', 'email']))->toBeTrue();

    // test arrays within query string
    $request = Request::create('/', 'GET', ['foo' => ['bar', 'baz']]);
    expect($request->filled('foo'))->toBeTrue();

    $request = Request::create('/', 'GET', ['foo' => ['bar' => 'baz']]);
    expect($request->filled('foo.bar'))->toBeTrue();
});

test('is not filled method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'age' => '', 'city' => null]);
    expect($request->isNotFilled('name'))->toBeFalse();
    expect($request->isNotFilled('age'))->toBeTrue();
    expect($request->isNotFilled('city'))->toBeTrue();
    expect($request->isNotFilled('foo'))->toBeTrue();
    expect($request->isNotFilled(['name', 'email']))->toBeFalse();
    expect($request->isNotFilled(['foo', 'age']))->toBeTrue();
    expect($request->isNotFilled(['age', 'city']))->toBeTrue();

    $request = Request::create('/', 'GET', ['foo' => ['bar', 'baz' => '0']]);
    expect($request->isNotFilled('foo'))->toBeFalse();
    expect($request->isNotFilled('foo.bar'))->toBeTrue();
    expect($request->isNotFilled('foo.baz'))->toBeFalse();
});

test('filled any method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'age' => '', 'city' => null]);

    expect($request->anyFilled(['name']))->toBeTrue();
    expect($request->anyFilled('name'))->toBeTrue();

    expect($request->anyFilled(['age']))->toBeFalse();
    expect($request->anyFilled('age'))->toBeFalse();

    expect($request->anyFilled(['foo']))->toBeFalse();
    expect($request->anyFilled('foo'))->toBeFalse();

    expect($request->anyFilled(['age', 'name']))->toBeTrue();
    expect($request->anyFilled('age', 'name'))->toBeTrue();

    expect($request->anyFilled(['foo', 'name']))->toBeTrue();
    expect($request->anyFilled('foo', 'name'))->toBeTrue();

    expect($request->anyFilled(['age', 'city']))->toBeFalse();
    expect($request->anyFilled('age', 'city'))->toBeFalse();

    expect($request->anyFilled(['foo', 'bar']))->toBeFalse();
    expect($request->anyFilled('foo', 'bar'))->toBeFalse();
});

test('input method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor']);
    expect($request->input('name'))->toBe('Taylor');
    expect($request['name'])->toBe('Taylor');
    expect($request->input('foo', 'Bob'))->toBe('Bob');

    $request = Request::create('/', 'GET', [], [], ['file' => new SymfonyUploadedFile(__FILE__, 'foo.php')]);
    expect($request['file'])->toBeInstanceOf(SymfonyUploadedFile::class);
});

test('fluent method', function () {
    $request = Request::create('/', 'GET', [
        'user' => [
            'name' => 'Michael',
            'role' => 'admin',
        ],
        'users' => null,
    ]);
    expect($request->fluent('user')->toArray())->toBe(['name' => 'Michael', 'role' => 'admin']);
    expect($request->fluent('users')->toArray())->toBe([]);
    expect($request->fluent('not_found')->toArray())->toBe([]);
});

test('string method', function () {
    $request = Request::create('/', 'GET', [
        'int' => 123,
        'int_str' => '456',
        'float' => 123.456,
        'float_str' => '123.456',
        'float_zero' => 0.000,
        'float_str_zero' => '0.000',
        'str' => 'abc',
        'empty_str' => '',
        'null' => null,
    ]);
    expect($request->string('int'))->toBeInstanceOf(\Voyager\NutsAndBolts\DataObjects\Stringable::class);
    expect($request->string('unknown_key'))->toBeInstanceOf(\Voyager\NutsAndBolts\DataObjects\Stringable::class);
    expect($request->string('int')->value())->toBe('123');
    expect($request->string('int_str')->value())->toBe('456');
    expect($request->string('float')->value())->toBe('123.456');
    expect($request->string('float_str')->value())->toBe('123.456');
    expect($request->string('float_zero')->value())->toBe('0');
    expect($request->string('float_str_zero')->value())->toBe('0.000');
    expect($request->string('empty_str')->value())->toBe('');
    expect($request->string('null')->value())->toBe('');
    expect($request->string('unknown_key')->value())->toBe('');
});

test('boolean method', function () {
    $request = Request::create('/', 'GET', ['with_trashed' => 'false', 'download' => true, 'checked' => 1, 'unchecked' => '0', 'with_on' => 'on', 'with_yes' => 'yes']);
    expect($request->boolean('checked'))->toBeTrue();
    expect($request->boolean('download'))->toBeTrue();
    expect($request->boolean('unchecked'))->toBeFalse();
    expect($request->boolean('with_trashed'))->toBeFalse();
    expect($request->boolean('some_undefined_key'))->toBeFalse();
    expect($request->boolean('with_on'))->toBeTrue();
    expect($request->boolean('with_yes'))->toBeTrue();
});

test('integer method', function () {
    $request = Request::create('/', 'GET', [
        'int' => '123',
        'raw_int' => 456,
        'zero_padded' => '078',
        'space_padded' => ' 901',
        'nan' => 'nan',
        'mixed' => '1ab',
        'underscore_notation' => '2_000',
        'null' => null,
    ]);
    expect($request->integer('int'))->toBe(123);
    expect($request->integer('raw_int'))->toBe(456);
    expect($request->integer('zero_padded'))->toBe(78);
    expect($request->integer('space_padded'))->toBe(901);
    expect($request->integer('nan'))->toBe(0);
    expect($request->integer('mixed'))->toBe(1);
    expect($request->integer('underscore_notation'))->toBe(2);
    expect($request->integer('unknown_key', 123456))->toBe(123456);
    expect($request->integer('null'))->toBe(0);
    expect($request->integer('null', 123456))->toBe(0);
});

test('float method', function () {
    $request = Request::create('/', 'GET', [
        'float' => '1.23',
        'raw_float' => 45.6,
        'decimal_only' => '.6',
        'zero_padded' => '0.78',
        'space_padded' => ' 90.1',
        'nan' => 'nan',
        'mixed' => '1.ab',
        'scientific_notation' => '1e3',
        'null' => null,
    ]);
    expect($request->float('float'))->toBe(1.23);
    expect($request->float('raw_float'))->toBe(45.6);
    expect($request->float('decimal_only'))->toBe(.6);
    expect($request->float('zero_padded'))->toBe(0.78);
    expect($request->float('space_padded'))->toBe(90.1);
    expect($request->float('nan'))->toBe(0.0);
    expect($request->float('mixed'))->toBe(1.0);
    expect($request->float('scientific_notation'))->toBe(1e3);
    expect($request->float('unknown_key', 123.456))->toBe(123.456);
    expect($request->float('null'))->toBe(0.0);
    expect($request->float('null', 123.456))->toBe(0.0);
});

test('array method', function () {
    $request = Request::create('/', 'GET', []);
    expect($request->array())->toBeArray();
    expect($request->array())->toBeEmpty();

    $request = Request::create('/', 'GET', [
        'users' => [1, 2, 3],
        'roles' => [4, 5, 6],
        'email' => 'test@example.com',
    ]);

    expect($request->array('missing'))->toBeEmpty();
    expect($request->array(['missing']))->toBeEmpty();
    expect($request->array('users'))->toEqual([1, 2, 3]);
    expect($request->array(['users']))->toEqual(['users' => [1, 2, 3]]);
    expect($request->array(['users', 'email']))->toEqual(['users' => [1, 2, 3], 'email' => 'test@example.com']);

    expect($request->array())->toEqual([
        'users' => [1, 2, 3],
        'roles' => [4, 5, 6],
        'email' => 'test@example.com',
    ]);
});

test('collect method', function () {
    $request = Request::create('/', 'GET', ['users' => [1, 2, 3]]);

    expect($request->collect('users'))->toBeInstanceOf(Collection::class);
    expect($request->collect('developers')->isEmpty())->toBeTrue();
    expect($request->collect('users')->all())->toEqual([1, 2, 3]);
    expect($request->collect()->all())->toEqual(['users' => [1, 2, 3]]);

    $request = Request::create('/', 'GET', ['text-payload']);
    expect($request->collect()->all())->toEqual(['text-payload']);

    $request = Request::create('/', 'GET', ['email' => 'test@example.com']);
    expect($request->collect('email')->all())->toEqual(['test@example.com']);

    $request = Request::create('/', 'GET', []);
    expect($request->collect())->toBeInstanceOf(Collection::class);
    expect($request->collect()->isEmpty())->toBeTrue();

    $request = Request::create('/', 'GET', ['users' => [1, 2, 3], 'roles' => [4, 5, 6], 'foo' => ['bar', 'baz'], 'email' => 'test@example.com']);
    expect($request->collect(['users']))->toBeInstanceOf(Collection::class);
    expect($request->collect(['developers'])->isEmpty())->toBeTrue();
    expect($request->collect(['roles'])->isNotEmpty())->toBeTrue();
    expect($request->collect(['roles'])->all())->toEqual(['roles' => [4, 5, 6]]);
    expect($request->collect(['users', 'email'])->all())->toEqual(['users' => [1, 2, 3], 'email' => 'test@example.com']);
    expect($request->collect(['roles', 'foo']))->toEqual(collect(['roles' => [4, 5, 6], 'foo' => ['bar', 'baz']]));
    expect($request->collect()->all())->toEqual(['users' => [1, 2, 3], 'roles' => [4, 5, 6], 'foo' => ['bar', 'baz'], 'email' => 'test@example.com']);
});

test('date method', function () {
    $request = Request::create('/', 'GET', [
        'as_null' => null,
        'as_invalid' => 'invalid',

        'as_datetime' => '20-01-01 16:30:25',
        'as_format' => '1577896225',
        'as_timezone' => '20-01-01 13:30:25',

        'as_date' => '2020-01-01',
        'as_time' => '16:30:25',
    ]);

    $current = Carbon::create(2020, 1, 1, 16, 30, 25);

    expect($request->date('as_null'))->toBeNull();
    expect($request->date('doesnt_exists'))->toBeNull();

    expect($request->date('as_datetime'))->toEqual($current);
    expect($request->date('as_format', 'U')->format('Y-m-d H:i:s P'))->toEqual($current->format('Y-m-d H:i:s P'));
    expect($request->date('as_timezone', null, 'America/Santiago'))->toEqual($current);

    expect($request->date('as_date')->isSameDay($current))->toBeTrue();
    expect($request->date('as_time')->isSameSecond('16:30:25'))->toBeTrue();
});

test('date method exception when value invalid', function () {

    $request = Request::create('/', 'GET', [
        'date' => 'invalid',
    ]);

    $request->date('date');
})->throws(InvalidArgumentException::class);

test('date method exception when format invalid', function () {

    $request = Request::create('/', 'GET', [
        'date' => '20-01-01 16:30:25',
    ]);

    $request->date('date', 'invalid_format');
})->throws(InvalidArgumentException::class);

test('interval method', function () {
    $request = Request::create('/', 'GET', [
        'as_null' => null,
        'as_empty' => '',
        'as_iso' => 'P1Y2M3DT4H5M6S',
        'as_human' => '2 hours 30 minutes',
        'as_seconds' => '90',
        'as_minutes' => '45',
    ]);

    expect($request->interval('as_null'))->toBeNull();
    expect($request->interval('as_empty'))->toBeNull();
    expect($request->interval('doesnt_exist'))->toBeNull();

    $interval = $request->interval('as_iso');
    expect($interval)->toBeInstanceOf(CarbonInterval::class);
    expect($interval->years)->toBe(1);
    expect($interval->months)->toBe(2);
    expect($interval->dayz)->toBe(3);
    expect($interval->hours)->toBe(4);
    expect($interval->minutes)->toBe(5);
    expect($interval->seconds)->toBe(6);

    $interval = $request->interval('as_human');
    expect($interval)->toBeInstanceOf(CarbonInterval::class);
    expect($interval->hours)->toBe(2);
    expect($interval->minutes)->toBe(30);

    $interval = $request->interval('as_seconds', 'second');
    expect($interval)->toBeInstanceOf(CarbonInterval::class);
    expect($interval->seconds)->toBe(90);

    expect($request->interval('as_seconds', 'minute')->minutes)->toBe(90);
    expect($request->interval('as_seconds', 'hour')->hours)->toBe(90);
    expect($request->interval('as_seconds', 'day')->dayz)->toBe(90);

    expect($request->interval('as_minutes', Unit::Minute)->minutes)->toBe(45);
    expect($request->interval('as_minutes', Unit::Second)->seconds)->toBe(45);
});

test('interval method with scientific notation floats', function () {
    $request = Request::create('/', 'GET', [
        'small_float' => '0.000053',
        'very_small' => '0.000001',
        'scientific' => '5.3E-5',
        'normal_float' => '1.5',
        'integer' => '90',
    ]);

    // Small floats that PHP would render in scientific notation (e.g. 5.3E-5)
    $interval = $request->interval('small_float', Unit::Millisecond);
    expect($interval)->toBeInstanceOf(CarbonInterval::class);

    $interval = $request->interval('very_small', Unit::Second);
    expect($interval)->toBeInstanceOf(CarbonInterval::class);

    // Scientific notation string passed directly
    $interval = $request->interval('scientific', Unit::Millisecond);
    expect($interval)->toBeInstanceOf(CarbonInterval::class);

    // Normal values should still work
    $interval = $request->interval('normal_float', Unit::Hour);
    expect($interval)->toBeInstanceOf(CarbonInterval::class);
    expect($interval->hours)->toBe(1);

    $interval = $request->interval('integer', Unit::Minute);
    expect($interval)->toBeInstanceOf(CarbonInterval::class);
    expect($interval->minutes)->toBe(90);
});

test('enum method', function () {
    $request = Request::create('/', 'GET', [
        'valid_enum_value' => 'test',
        'invalid_enum_value' => 'invalid',
        'empty_value_request' => '',
        'string' => [
            'minus_1' => '-1',
            '0' => '0',
            'plus_1' => '1',
            'doesnt_exist' => '-1024',
        ],
        'int' => [
            'minus_1' => -1,
            '0' => 0,
            'plus_1' => 1,
            'doesnt_exist' => 1024,
        ],
    ]);

    expect($request->enum('doesnt_exist', TestEnumBacked::class))->toBeNull();

    expect($request->enum('invalid_enum_value', TestEnumBacked::class, TestEnumBacked::test))->toEqual(TestEnumBacked::test);
    expect($request->enum('missing_key', TestEnumBacked::class, TestEnumBacked::test))->toEqual(TestEnumBacked::test);

    expect($request->enum('valid_enum_value', TestEnumBacked::class))->toEqual(TestEnumBacked::test);

    expect($request->enum('invalid_enum_value', TestEnumBacked::class))->toBeNull();
    expect($request->enum('empty_value_request', TestEnumBacked::class))->toBeNull();
    expect($request->enum('valid_enum_value', TestEnum::class))->toBeNull();

    expect($request->enum('string.minus_1', TestIntegerEnumBacked::class))->toEqual(TestIntegerEnumBacked::minus_1);
    expect($request->enum('string.0', TestIntegerEnumBacked::class))->toEqual(TestIntegerEnumBacked::zero);
    expect($request->enum('string.plus_1', TestIntegerEnumBacked::class))->toEqual(TestIntegerEnumBacked::plus_1);
    expect($request->enum('string.doesnt_exist', TestIntegerEnumBacked::class))->toBeNull();
    expect($request->enum('int.minus_1', TestIntegerEnumBacked::class))->toEqual(TestIntegerEnumBacked::minus_1);
    expect($request->enum('int.0', TestIntegerEnumBacked::class))->toEqual(TestIntegerEnumBacked::zero);
    expect($request->enum('int.plus_1', TestIntegerEnumBacked::class))->toEqual(TestIntegerEnumBacked::plus_1);
    expect($request->enum('int.doesnt_exist', TestIntegerEnumBacked::class))->toBeNull();
});

test('enums method', function () {
    $request = Request::create('/', 'GET', [
        'valid_enum_values' => ['test', 'test'],
        'invalid_enum_values' => ['invalid', 'invalid'],
        'empty_value_request' => [],
        'string' => [
            'minus_1' => ['-1', '0'],
            '0' => '0',
            'plus_1' => '1',
            'doesnt_exist' => '-1024',
        ],
        'int' => [
            'minus_1' => -1,
            '0' => 0,
            'plus_1' => 1,
            'doesnt_exist' => 1024,
        ],
    ]);

    expect($request->enums('doesnt_exist', TestEnumBacked::class))->toBeEmpty();

    expect($request->enums('valid_enum_values', TestEnumBacked::class))->toEqual([TestEnumBacked::test, TestEnumBacked::test]);

    expect($request->enums('invalid_enum_value', TestEnumBacked::class))->toBeEmpty();
    expect($request->enums('empty_value_request', TestEnumBacked::class))->toBeEmpty();
    expect($request->enums('valid_enum_value', TestEnum::class))->toBeEmpty();

    expect($request->enums('string.minus_1', TestIntegerEnumBacked::class))->toEqual([TestIntegerEnumBacked::minus_1, TestIntegerEnumBacked::zero]);
    expect($request->enums('string.0', TestIntegerEnumBacked::class))->toEqual([TestIntegerEnumBacked::zero]);
    expect($request->enums('string.plus_1', TestIntegerEnumBacked::class))->toEqual([TestIntegerEnumBacked::plus_1]);
    expect($request->enums('string.doesnt_exist', TestIntegerEnumBacked::class))->toBeEmpty();
    expect($request->enums('int.minus_1', TestIntegerEnumBacked::class))->toEqual([TestIntegerEnumBacked::minus_1]);
    expect($request->enums('int.0', TestIntegerEnumBacked::class))->toEqual([TestIntegerEnumBacked::zero]);
    expect($request->enums('int.plus_1', TestIntegerEnumBacked::class))->toEqual([TestIntegerEnumBacked::plus_1]);
    expect($request->enums('int.doesnt_exist', TestIntegerEnumBacked::class))->toBeEmpty();
});

test('array access', function () {
    $request = Request::create('/', 'GET', ['name' => null, 'foo' => ['bar' => null, 'baz' => '']]);

    $request->setRouteResolver(function () use ($request) {
        $route = new Route('GET', '/foo/bar/{id}/{name}', []);
        $route->bind($request);
        $route->setParameter('id', 'foo');
        $route->setParameter('name', 'Taylor');

        return $route;
    });

    expect(isset($request['non-existent']))->toBeFalse();
    expect($request['non-existent'])->toBeNull();

    expect(isset($request['name']))->toBeTrue();
    expect($request['name'])->toBeNull();

    expect($request['name'])->not->toBe('Taylor');

    expect(isset($request['foo.bar']))->toBeTrue();
    expect($request['foo.bar'])->toBeNull();
    expect(isset($request['foo.baz']))->toBeTrue();
    expect($request['foo.baz'])->toBe('');

    expect(isset($request['id']))->toBeTrue();
    expect($request['id'])->toBe('foo');
});

test('array access without route resolver', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor']);

    expect(isset($request['non-existent']))->toBeFalse();
    expect($request['non-existent'])->toBeNull();

    expect(isset($request['name']))->toBeTrue();
    expect($request['name'])->toBe('Taylor');
});

test('all method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'age' => null]);
    expect($request->all('name', 'age', 'email'))->toEqual(['name' => 'Taylor', 'age' => null, 'email' => null]);
    expect($request->all('name'))->toEqual(['name' => 'Taylor']);
    expect($request->all())->toEqual(['name' => 'Taylor', 'age' => null]);

    $request = Request::create('/', 'GET', ['developer' => ['name' => 'Taylor', 'age' => null]]);
    expect($request->all('developer.name', 'developer.skills'))->toEqual(['developer' => ['name' => 'Taylor', 'skills' => null]]);
    expect($request->all(['developer.name', 'developer.skills']))->toEqual(['developer' => ['name' => 'Taylor', 'skills' => null]]);
    expect($request->all('developer.age'))->toEqual(['developer' => ['age' => null]]);
    expect($request->all('developer.skills'))->toEqual(['developer' => ['skills' => null]]);
    expect($request->all())->toEqual(['developer' => ['name' => 'Taylor', 'age' => null]]);
});

test('keys method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'age' => null]);
    expect($request->keys())->toEqual(['name', 'age']);

    $files = [
        'foo' => [
            'size' => 500,
            'name' => 'foo.jpg',
            'tmp_name' => __FILE__,
            'type' => 'blah',
            'error' => null,
        ],
    ];
    $request = Request::create('/', 'GET', [], [], $files);
    expect($request->keys())->toEqual(['foo']);

    $request = Request::create('/', 'GET', ['name' => 'Taylor'], [], $files);
    expect($request->keys())->toEqual(['name', 'foo']);
});

test('only method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'age' => null]);
    expect($request->only('name', 'age', 'email'))->toEqual(['name' => 'Taylor', 'age' => null]);

    $request = Request::create('/', 'GET', ['developer' => ['name' => 'Taylor', 'age' => null]]);
    expect($request->only('developer.name', 'developer.skills'))->toEqual(['developer' => ['name' => 'Taylor']]);
    expect($request->only('developer.age'))->toEqual(['developer' => ['age' => null]]);
    expect($request->only('developer.skills'))->toBe([]);
});

test('except method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'age' => 25]);
    expect($request->except('age'))->toEqual(['name' => 'Taylor']);
    expect($request->except('age', 'name'))->toBe([]);
    expect($request->except(['age', 'name']))->toBe([]);
});

test('query method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor']);
    expect($request->query())->toBe(['name' => 'Taylor']);
    expect($request->query('name'))->toBe('Taylor');
    expect($request->query('name', 'Amir'))->toBe('Taylor');
    expect($request->query('foo', 'Bob'))->toBe('Bob');
    $all = $request->query(null);
    expect($all['name'])->toBe('Taylor');

    $request = Request::create('/', 'GET', ['hello' => 'world', 'user' => ['Taylor', 'Mohamed Said']]);
    expect($request->query('user'))->toBe(['Taylor', 'Mohamed Said']);
    expect($request->query->all())->toBe(['hello' => 'world', 'user' => ['Taylor', 'Mohamed Said']]);

    $request = Request::create('/?hello=world&user[]=Taylor&user[]=Mohamed%20Said', 'GET', []);
    expect($request->query('user'))->toBe(['Taylor', 'Mohamed Said']);
    expect($request->query->all())->toBe(['hello' => 'world', 'user' => ['Taylor', 'Mohamed Said']]);
});

test('post method', function () {
    $request = Request::create('/', 'POST', ['name' => 'Taylor']);
    expect($request->post())->toBe(['name' => 'Taylor']);
    expect($request->post('name'))->toBe('Taylor');
    expect($request->post('name', 'Amir'))->toBe('Taylor');
    expect($request->post('foo', 'Bob'))->toBe('Bob');
    $all = $request->post(null);
    expect($all['name'])->toBe('Taylor');
});

test('cookie method', function () {
    $request = Request::create('/', 'GET', [], ['name' => 'Taylor']);
    expect($request->cookie())->toBe(['name' => 'Taylor']);
    expect($request->cookie('name'))->toBe('Taylor');
    expect($request->cookie('name', 'Amir'))->toBe('Taylor');
    expect($request->cookie('foo', 'Bob'))->toBe('Bob');
    $all = $request->cookie(null);
    expect($all['name'])->toBe('Taylor');
});

test('has cookie method', function () {
    $request = Request::create('/', 'GET', [], ['foo' => 'bar']);
    expect($request->hasCookie('foo'))->toBeTrue();
    expect($request->hasCookie('qu'))->toBeFalse();
});

test('file method', function () {
    $files = [
        'foo' => [
            'size' => 500,
            'name' => 'foo.jpg',
            'tmp_name' => __FILE__,
            'type' => 'blah',
            'error' => null,
        ],
    ];
    $request = Request::create('/', 'GET', [], [], $files);
    expect($request->file('foo'))->toBeInstanceOf(SymfonyUploadedFile::class);
});

test('image method', function () {
    $files = [
        'avatar' => [
            'size' => 500,
            'name' => 'avatar.jpg',
            'tmp_name' => __FILE__,
            'type' => 'image/jpeg',
            'error' => null,
        ],
    ];
    $request = Request::create('/', 'GET', [], [], $files);
    expect($request->image('avatar'))->toBeInstanceOf(Image::class);
});

test('image method returns null for missing key', function () {
    $request = Request::create('/', 'GET', [], [], []);
    expect($request->image('avatar'))->toBeNull();
});

test('has file method', function () {
    $request = Request::create('/', 'GET', [], [], []);
    expect($request->hasFile('foo'))->toBeFalse();

    $files = [
        'foo' => [
            'size' => 500,
            'name' => 'foo.jpg',
            'tmp_name' => __FILE__,
            'type' => 'blah',
            'error' => null,
        ],
    ];
    $request = Request::create('/', 'GET', [], [], $files);
    expect($request->hasFile('foo'))->toBeTrue();
    expect($request->hasFile('bar'))->toBeFalse();
});

test('server method', function () {
    $request = Request::create('/', 'GET', [], [], [], ['foo' => 'bar']);
    expect($request->server('foo'))->toBe('bar');
    expect($request->server('foo.doesnt.exist', 'bar'))->toBe('bar');
    $all = $request->server(null);
    expect($all['foo'])->toBe('bar');
});

test('merge method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor']);
    $merge = ['buddy' => 'Dayle'];
    $request->merge($merge);
    expect($request->input('name'))->toBe('Taylor');
    expect($request->input('buddy'))->toBe('Dayle');
});

test('merge method treats asterisks as literal keys', function () {
    $request = Request::create('/', 'GET', []);
    $request->merge(['*' => 226]);
    expect($request->all()['*'])->toBe(226);

    $request = Request::create('/', 'GET', ['organisation_id' => 10, 'name' => 'Taylor']);
    $request->merge(['*' => 226]);
    expect($request->input('organisation_id'))->toBe(10);
    expect($request->input('name'))->toBe('Taylor');
    expect($request->all()['*'])->toBe(226);

    $request = Request::create('/', 'GET', ['profile' => ['name' => 'Taylor', 'email' => 'taylor@laravel.com']]);
    $request->merge(['profile.*' => 'masked']);
    expect($request->input('profile.name'))->toBe('Taylor');
    expect($request->input('profile.email'))->toBe('taylor@laravel.com');
    expect($request->all()['profile']['*'])->toBe('masked');

    $request = Request::create('/', 'GET', ['profile' => ['name' => 'Taylor', 'email' => 'taylor@laravel.com']]);
    $request->merge(['profile.name' => 'Otwell']);
    expect($request->input('profile.name'))->toBe('Otwell');
    expect($request->input('profile.email'))->toBe('taylor@laravel.com');
});

test('merge method treats asterisks as literal keys on json requests', function () {
    $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode(['organisation_id' => 10, 'name' => 'Taylor']));
    $request->merge(['*' => 226]);
    expect($request->input('organisation_id'))->toBe(10);
    expect($request->input('name'))->toBe('Taylor');
    expect($request->all()['*'])->toBe(226);
});

test('merge if missing method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor']);
    $merge = ['boolean_setting' => 0];
    $request->mergeIfMissing($merge);
    expect($request->input('name'))->toBe('Taylor');
    expect($request->input('boolean_setting'))->toBe(0);

    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'boolean_setting' => 1]);
    $merge = ['boolean_setting' => 0];
    $request->mergeIfMissing($merge);
    expect($request->input('name'))->toBe('Taylor');
    expect($request->input('boolean_setting'))->toBe(1);

    $request = Request::create('/', 'GET', ['user' => ['first_name' => 'Taylor', 'email' => 'taylor@laravel.com']]);
    $merge = ['user.last_name' => 'Otwell'];
    $request->mergeIfMissing($merge);
    expect($request->input('user.last_name'))->toBe('Otwell');

    $request = Request::create('/', 'GET', ['user' => ['first_name' => 'Taylor', 'email' => 'taylor@laravel.com']]);
    $merge = ['user.first_name' => 'John'];
    $request->mergeIfMissing($merge);
    expect($request->input('user.first_name'))->toBe('Taylor');
});

test('merge if missing method treats asterisks as literal keys', function () {
    $request = Request::create('/', 'GET', ['organisation_id' => 10, 'name' => 'Taylor']);
    $request->mergeIfMissing(['*' => 226]);
    expect($request->input('organisation_id'))->toBe(10);
    expect($request->input('name'))->toBe('Taylor');
    expect($request->all()['*'])->toBe(226);
});

test('replace method', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor']);
    $replace = ['buddy' => 'Dayle'];
    $request->replace($replace);
    expect($request->input('name'))->toBeNull();
    expect($request->input('buddy'))->toBe('Dayle');
});

test('offset unset method', function () {
    $request = Request::create('/', 'HEAD', ['name' => 'Taylor']);
    $request->offsetUnset('name');
    expect($request->input('name'))->toBeNull();
});

test('header method', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_DO_THIS' => 'foo']);
    expect($request->header('do-this'))->toBe('foo');
    expect($request->header('do-that', 'default'))->toBe('default');
    $all = $request->header(null);
    expect($all['do-this'][0])->toBe('foo');
});

test('bearer token method', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer fooBearerbar']);
    expect($request->bearerToken())->toBe('fooBearerbar');

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'bearer fooBearerbar']);
    expect($request->bearerToken())->toBe('fooBearerbar');

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Basic foo, Bearer bar']);
    expect($request->bearerToken())->toBe('bar');

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'Bearer foo,bar']);
    expect($request->bearerToken())->toBe('foo');

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'bearer foo,bar']);
    expect($request->bearerToken())->toBe('foo');

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_AUTHORIZATION' => 'foo,bar']);
    expect($request->bearerToken())->toBeNull();
});

test('jsonmethod', function () {
    $payload = ['name' => 'taylor'];
    $request = Request::create('/', 'GET', [], [], [], ['CONTENT_TYPE' => 'application/json'], json_encode($payload));
    expect($request->json('name'))->toBe('taylor');
    expect($request->json('name', 'Otwell'))->toBe('taylor');
    expect($request->json('family', 'Moharami'))->toBe('Moharami');
    expect($request->input('name'))->toBe('taylor');
    $data = $request->json()->all();
    expect($data)->toEqual($payload);
});

test('jsonmethod decodes top level zero', function () {
    $request = Request::create('/', 'POST', [], [], [], ['CONTENT_TYPE' => 'application/json'], '0');

    expect($request->json()->all())->toBe([0]);
});

test('jsonemulating phpbuilt in server', function () {
    $payload = ['name' => 'taylor'];
    $content = json_encode($payload);
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_CONTENT_TYPE' => 'application/json', 'HTTP_CONTENT_LENGTH' => strlen($content)], $content);
    expect($request->isJson())->toBeTrue();
    $data = $request->json()->all();
    expect($data)->toEqual($payload);

    $data = $request->all();
    expect($data)->toEqual($payload);
});

dataset('getPrefersCases', function () {
    return [
        ['application/json', ['json'], 'json'],
        ['application/json', ['html', 'json'], 'json'],
        ['application/foo+json', 'application/foo+json', 'application/foo+json'],
        ['application/foo+json', 'json', 'json'],
        ['application/json;q=0.5, text/html;q=1.0', ['json', 'html'], 'html'],
        ['application/json;q=0.5, text/plain;q=1.0, text/html;q=1.0', ['json', 'txt', 'html'], 'txt'],
        ['application/*', 'json', 'json'],
        ['application/json; charset=utf-8', 'json', 'json'],
        ['application/xml; charset=utf-8', ['html', 'json'], null],
        ['application/json, text/html', ['html', 'json'], 'json'],
        ['application/json;q=0.4, text/html;q=0.6', ['html', 'json'], 'html'],

        ['application/json; charset=utf-8', 'application/json', 'application/json'],
        ['application/json, text/html', ['text/html', 'application/json'], 'application/json'],
        ['application/json;q=0.4, text/html;q=0.6', ['text/html', 'application/json'], 'text/html'],
        ['application/json;q=0.4, text/html;q=0.6', ['application/json', 'text/html'], 'text/html'],

        ['*/*; charset=utf-8', 'json', 'json'],
        ['application/*', 'application/json', 'application/json'],
        ['application/*', 'application/xml', 'application/xml'],
        ['application/*', 'text/html', null],
    ];
});

test('prefers method', function ($accept, $prefers, $expected) {
    expect(Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => $accept])->prefers($prefers))->toBe($expected);
})->with('getPrefersCases');

test('all input returns input and files', function () {
    $file = $this->getMockBuilder(UploadedFile::class)->setConstructorArgs([__FILE__, 'photo.jpg'])->getMock();
    $request = Request::create('/?boom=breeze', 'GET', ['foo' => 'bar'], [], ['baz' => $file]);
    expect($request->all())->toEqual(['foo' => 'bar', 'baz' => $file, 'boom' => 'breeze']);
});

test('all input returns nested input and files', function () {
    $file = $this->getMockBuilder(UploadedFile::class)->setConstructorArgs([__FILE__, 'photo.jpg'])->getMock();
    $request = Request::create('/?boom=breeze', 'GET', ['foo' => ['bar' => 'baz']], [], ['foo' => ['photo' => $file]]);
    expect($request->all())->toEqual(['foo' => ['bar' => 'baz', 'photo' => $file], 'boom' => 'breeze']);
});

test('all input prefers input over files for colliding keys', function () {
    $file = new SymfonyUploadedFile(__FILE__, 'email.txt');
    $request = Request::create('/', 'POST', ['email' => 'taylor@laravel.com'], [], ['email' => $file]);

    expect($request->all())->toBe(['email' => 'taylor@laravel.com']);
    expect($request->only('email'))->toBe(['email' => 'taylor@laravel.com']);
    expect($request->file('email'))->toBeInstanceOf(UploadedFile::class);
});

test('all input preserves input key order when files are merged', function () {
    $file = new SymfonyUploadedFile(__FILE__, 'photo.jpg');
    $request = Request::create('/', 'POST', [
        'items' => [0 => ['name' => 'first'], 1 => ['name' => 'second']],
    ], [], [
        'items' => [1 => ['photo' => $file]],
    ]);

    $items = $request->all()['items'];

    expect(array_keys($items))->toBe([0, 1]);
    expect($items[1]['name'])->toBe('second');
    expect($items[1]['photo'])->toBeInstanceOf(UploadedFile::class);
});

test('all input returns input after replace', function () {
    $request = Request::create('/?boom=breeze', 'GET', ['foo' => ['bar' => 'baz']]);
    $request->replace(['foo' => ['bar' => 'baz'], 'boom' => 'breeze']);
    expect($request->all())->toEqual(['foo' => ['bar' => 'baz'], 'boom' => 'breeze']);
});

test('all input with numeric keys returns input after replace', function () {
    $request1 = Request::create('/', 'POST', [0 => 'A', 1 => 'B', 2 => 'C']);
    $request1->replace([0 => 'A', 1 => 'B', 2 => 'C']);
    expect($request1->all())->toEqual([0 => 'A', 1 => 'B', 2 => 'C']);

    $request2 = Request::create('/', 'POST', [1 => 'A', 2 => 'B', 3 => 'C']);
    $request2->replace([1 => 'A', 2 => 'B', 3 => 'C']);
    expect($request2->all())->toEqual([1 => 'A', 2 => 'B', 3 => 'C']);
});

test('input with empty filename', function () {
    $invalidFiles = [
        'file' => [
            'name' => null,
            'type' => null,
            'tmp_name' => null,
            'error' => 4,
            'size' => 0,
        ],
    ];

    $baseRequest = SymfonyRequest::create('/?boom=breeze', 'GET', ['foo' => ['bar' => 'baz']], [], $invalidFiles);

    $request = Request::createFromBase($baseRequest);

    expect($request->file('file'))->toBeNull();
});

test('multiple file upload with empty value', function () {
    $invalidFiles = [
        'file' => [
            'name' => [''],
            'type' => [''],
            'tmp_name' => [''],
            'error' => [4],
            'size' => [0],
        ],
    ];

    $baseRequest = SymfonyRequest::create('/?boom=breeze', 'GET', ['foo' => ['bar' => 'baz']], [], $invalidFiles);

    $request = Request::createFromBase($baseRequest);

    expect($request->files->all())->toBeEmpty();
});

test('old method calls session', function () {
    $request = Request::create('/');
    $session = new Store('test', new NullSessionHandler);
    $session->flashInput(['foo' => 'boom']);
    $request->setMarinerSession($session);
    expect($request->old('foo', 'bar'))->toBe('boom');
});

test('old method calls session when default is array', function () {
    $request = Request::create('/');
    $session = new Store('test', new NullSessionHandler);
    $request->setMarinerSession($session);
    expect($request->old('foo', ['bar']))->toBe(['bar']);
});

test('old method can get default value from model by key', function () {
    $request = Request::create('/');
    $model = new class extends Model
    {
        protected $table = 'prices';
    };
    $model->name = 'foobar';
    $session = new Store('test', new NullSessionHandler);
    $request->setMarinerSession($session);
    expect($request->old('name', $model))->toBe('foobar');
});

test('flush method calls session', function () {
    $request = Request::create('/');
    $session = new Store('test', new NullSessionHandler);
    $session->flashInput(['foo' => 'bar']);
    $session->ageFlashData();
    $request->setMarinerSession($session);
    $request->flush();
    expect($session->getOldInput())->toBe([]);
    expect($session->get('_flash.new', []))->toContain('_old_input');
});

test('expects json', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    expect($request->expectsJson())->toBeTrue();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '*/*']);
    expect($request->expectsJson())->toBeFalse();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '*/*', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    expect($request->expectsJson())->toBeTrue();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => null, 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    expect($request->expectsJson())->toBeTrue();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '*/*', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_X_PJAX' => 'true']);
    expect($request->expectsJson())->toBeFalse();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html']);
    expect($request->expectsJson())->toBeFalse();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest']);
    expect($request->expectsJson())->toBeFalse();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html', 'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest', 'HTTP_X_PJAX' => 'true']);
    expect($request->expectsJson())->toBeFalse();
});

test('format returns acceptable format', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    expect($request->format())->toBe('json');
    expect($request->wantsJson())->toBeTrue();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json; charset=utf-8']);
    expect($request->format())->toBe('json');
    expect($request->wantsJson())->toBeTrue();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/atom+xml']);
    expect($request->format())->toBe('atom');
    expect($request->wantsJson())->toBeFalse();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'is/not/known']);
    expect($request->format())->toBe('html');
    expect($request->format('foo'))->toBe('foo');
});

test('wants markdown', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/markdown']);
    expect($request->wantsMarkdown())->toBeTrue();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/markdown; charset=utf-8']);
    expect($request->wantsMarkdown())->toBeTrue();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    expect($request->wantsMarkdown())->toBeFalse();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html']);
    expect($request->wantsMarkdown())->toBeFalse();
});

test('accepts markdown', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/markdown']);
    expect($request->acceptsMarkdown())->toBeTrue();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html, text/markdown']);
    expect($request->wantsMarkdown())->toBeFalse();
    expect($request->acceptsMarkdown())->toBeTrue();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    expect($request->acceptsMarkdown())->toBeFalse();
});

test('matches type', function () {
    expect(Request::matchesType('application/json', 'application/json'))->toBeTrue();

    expect(Request::matchesType('application/json', 'application/vnd.api+json'))->toBeTrue();
    expect(Request::matchesType('application/xml', 'application/atom+xml'))->toBeTrue();

    expect(Request::matchesType('application/vnd.api+json', 'application/json'))->toBeFalse();
    expect(Request::matchesType('application/json', 'application/xml'))->toBeFalse();
    expect(Request::matchesType('application/json', 'text/json'))->toBeFalse();
    expect(Request::matchesType('json', 'application/json'))->toBeFalse();
});

test('format returns accepts json', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    expect($request->format())->toBe('json');
    expect($request->accepts('application/json'))->toBeTrue();
    expect($request->accepts('application/baz+json'))->toBeTrue();
    expect($request->acceptsJson())->toBeTrue();
    expect($request->acceptsHtml())->toBeFalse();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/foo+json']);
    expect($request->accepts('application/foo+json'))->toBeTrue();
    expect($request->accepts('application/bar+json'))->toBeFalse();
    expect($request->accepts('application/json'))->toBeFalse();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/*']);
    expect($request->accepts('application/xml'))->toBeTrue();
    expect($request->accepts('application/json'))->toBeTrue();
});

test('format returns accepts html', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html']);
    expect($request->format())->toBe('html');
    expect($request->accepts('text/html'))->toBeTrue();
    expect($request->acceptsHtml())->toBeTrue();
    expect($request->acceptsJson())->toBeFalse();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/*']);
    expect($request->accepts('text/html'))->toBeTrue();
    expect($request->accepts('text/plain'))->toBeTrue();
});

test('format returns accepts all', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '*/*']);
    expect($request->format())->toBe('html');
    expect($request->accepts('text/html'))->toBeTrue();
    expect($request->accepts('foo/bar'))->toBeTrue();
    expect($request->accepts('application/baz+xml'))->toBeTrue();
    expect($request->acceptsHtml())->toBeTrue();
    expect($request->acceptsJson())->toBeTrue();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '*']);
    expect($request->format())->toBe('html');
    expect($request->accepts('text/html'))->toBeTrue();
    expect($request->accepts('foo/bar'))->toBeTrue();
    expect($request->accepts('application/baz+xml'))->toBeTrue();
    expect($request->acceptsHtml())->toBeTrue();
    expect($request->acceptsJson())->toBeTrue();
});

test('format returns accepts multiple', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json,text/*']);
    expect($request->accepts(['text/html', 'application/json']))->toBeTrue();
    expect($request->accepts('text/html'))->toBeTrue();
    expect($request->accepts('text/foo'))->toBeTrue();
    expect($request->accepts('application/json'))->toBeTrue();
    expect($request->accepts('application/baz+json'))->toBeTrue();
});

test('format returns accepts charset', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json; charset=utf-8']);
    expect($request->accepts(['text/html', 'application/json']))->toBeTrue();
    expect($request->accepts('text/html'))->toBeFalse();
    expect($request->accepts('text/foo'))->toBeFalse();
    expect($request->accepts('application/json'))->toBeTrue();
    expect($request->accepts('application/baz+json'))->toBeTrue();
});

test('wants json respects header changes', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '*/*']);

    expect($request->wantsJson())->toBeFalse();

    expect($request->acceptsAnyContentType())->toBeTrue();

    $request->headers->set('Accept', 'application/json');

    expect($request->wantsJson())->toBeTrue();
});

test('accepts json respects header changes', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '*/*']);

    expect($request->acceptsAnyContentType())->toBeTrue();

    $request->headers->set('Accept', 'application/json');

    expect($request->acceptsJson())->toBeTrue();
});

test('prefers respects header changes', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '*/*']);

    expect($request->acceptsAnyContentType())->toBeTrue();

    $request->headers->set('Accept', 'application/json');

    expect($request->prefers(['html', 'json']))->toBe('json');
});

test('wants json works when header set before first call', function () {
    $request = Request::create('/', 'GET', [], [], [], []);

    $request->headers->set('Accept', 'application/json');

    expect($request->wantsJson())->toBeTrue();
});

test('cache cleared when transitioning from unset to set header', function () {
    $request = Request::create('/', 'GET', [], [], [], []);

    $request->getAcceptableContentTypes();

    $request->headers->set('Accept', 'application/json');

    expect($request->wantsJson())->toBeTrue();

    expect($request->acceptsJson())->toBeTrue();
});

test('accepts json works when header changed multiple times', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'text/html']);

    expect($request->acceptsJson())->toBeFalse();

    $request->headers->set('Accept', 'application/json');
    expect($request->acceptsJson())->toBeTrue();

    $request->headers->set('Accept', 'text/html');
    expect($request->acceptsJson())->toBeFalse();

    $request->headers->set('Accept', 'application/json');
    expect($request->acceptsJson())->toBeTrue();
});

test('bad accept header', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'Mozilla/5.0 (Windows; U; Windows NT 5.1; pt-PT; rv:1.9.1.2) Gecko/20090729 Firefox/3.5.2 (.NET CLR 3.5.30729)']);
    expect($request->accepts(['text/html', 'application/json']))->toBeFalse();
    expect($request->accepts('text/html'))->toBeFalse();
    expect($request->accepts('text/foo'))->toBeFalse();
    expect($request->accepts('application/json'))->toBeFalse();
    expect($request->accepts('application/baz+json'))->toBeFalse();
    expect($request->acceptsHtml())->toBeFalse();
    expect($request->acceptsJson())->toBeFalse();

    // Should not be handled as regex.
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '.+/.+']);
    expect($request->accepts('application/json'))->toBeFalse();
    expect($request->accepts('application/baz+json'))->toBeFalse();

    // Should not produce compilation error on invalid regex.
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => '(/(']);
    expect($request->accepts('text/html'))->toBeFalse();
});

test('case insensitive accept header', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'APPLICATION/JSON']);
    expect($request->accepts(['text/html', 'application/json']))->toBeTrue();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'AppLiCaTion/JsOn']);
    expect($request->accepts(['text/html', 'application/json']))->toBeTrue();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'APPLICATION/*']);
    expect($request->accepts(['text/html', 'application/json']))->toBeTrue();

    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'APPLICATION/JSON']);
    expect($request->expectsJson())->toBeTrue();
});

test('session method', function () {

    $request = Request::create('/');
    $request->session();
})->throws(RuntimeException::class, 'Session store not set on request.');

test('has session method', function () {
    $request = Request::create('/');

    expect($request->hasSession())->toBeFalse();

    $session = new Store('test', new NullSessionHandler);
    $request->setMarinerSession($session);

    expect($request->hasSession())->toBeTrue();
});

test('get session method with laravel session', function () {
    $request = Request::create('/');

    $laravelSession = new Store('test', new NullSessionHandler);
    $request->setMarinerSession($laravelSession);

    $session = $request->getSession();
    expect($session)->toBeInstanceOf(SessionInterface::class);

    expect($session->start())->toBeTrue();
    expect($laravelSession->isStarted())->toBeTrue();
});

test('get session method without laravel session', function () {

    $request = Request::create('/');

    $request->getSession();
})->throws(SessionNotFoundException::class, 'There is currently no session available.');

test('user resolver makes user available as magic property', function () {
    $request = Request::create('/', 'GET', [], [], [], ['HTTP_ACCEPT' => 'application/json']);
    $request->setUserResolver(function () {
        return 'user';
    });
    expect($request->user())->toBe('user');
});

test('fingerprint method', function () {
    $request = Request::create('/', 'GET', [], [], [], []);
    $request->setRouteResolver(function () use ($request) {
        $route = new Route('GET', '/foo/bar/{id}', []);
        $route->bind($request);

        return $route;
    });

    expect(mb_strlen($request->fingerprint()))->toEqual(40);
});

test('fingerprint without route', function () {

    $request = Request::create('/', 'GET', [], [], [], []);
    $request->fingerprint();
})->throws(RuntimeException::class, 'Unable to generate fingerprint. Route unavailable.');

test('json request fills request body params', function () {
    $body = [
        'foo' => 'bar',
        'baz' => ['qux'],
    ];

    $server = [
        'CONTENT_TYPE' => 'application/json',
    ];

    $base = SymfonyRequest::create('/', 'GET', [], [], [], $server, json_encode($body));

    $request = Request::createFromBase($base);

    expect($body)->toEqual($request->request->all());
});

test('non json request doesnt fill request body params', function () {
    $params = ['foo' => 'bar'];

    $getRequest = Request::create('/', 'GET', $params, [], [], []);
    expect($getRequest->request->all())->toBe([]);
    expect($params)->toEqual($getRequest->query->all());

    $postRequest = Request::create('/', 'POST', $params, [], [], []);
    expect($params)->toEqual($postRequest->request->all());
    expect($postRequest->query->all())->toBe([]);
});

test('magic methods', function () {
    // Simulates QueryStrings.
    $request = Request::create('/', 'GET', ['foo' => 'bar', 'empty' => '']);

    // Parameter 'foo' is 'bar', then it ISSET and is NOT EMPTY.
    expect($request->foo)->toBe('bar');
    expect(isset($request->foo))->toBeTrue();
    expect($request->foo)->not->toBeEmpty();

    // Parameter 'empty' is '', then it ISSET and is EMPTY.
    expect($request->empty)->toBe('');
    expect(isset($request->empty))->toBeTrue();
    expect($request->empty)->toBeEmpty();

    // Parameter 'undefined' is undefined/null, then it NOT ISSET and is EMPTY.
    expect($request->undefined)->toBeNull();
    expect(isset($request->undefined))->toBeFalse();
    expect($request->undefined)->toBeEmpty();

    // Simulates Route parameters.
    $request = Request::create('/example/bar', 'GET', ['xyz' => 'overwritten']);
    $request->setRouteResolver(function () use ($request) {
        $route = new Route('GET', '/example/{foo}/{xyz?}/{undefined?}', []);
        $route->bind($request);

        return $route;
    });

    // Router parameter 'foo' is 'bar', then it ISSET and is NOT EMPTY.
    expect($request->foo)->toBe('bar');
    expect($request['foo'])->toBe('bar');
    expect(isset($request->foo))->toBeTrue();
    expect($request->foo)->not->toBeEmpty();

    // Router parameter 'undefined' is undefined/null, then it NOT ISSET and is EMPTY.
    expect($request->undefined)->toBeNull();
    expect(isset($request->undefined))->toBeFalse();
    expect($request->undefined)->toBeEmpty();

    // Special case: router parameter 'xyz' is 'overwritten' by QueryString, then it ISSET and is NOT EMPTY.
    // Basically, QueryStrings have priority over router parameters.
    expect($request->xyz)->toBe('overwritten');
    expect(isset($request->foo))->toBeTrue();
    expect($request->foo)->not->toBeEmpty();

    // Simulates empty QueryString and Routes.
    $request = Request::create('/');
    $request->setRouteResolver(function () use ($request) {
        $route = new Route('GET', '/', []);
        $route->bind($request);

        return $route;
    });

    // Parameter 'undefined' is undefined/null, then it NOT ISSET and is EMPTY.
    expect($request->undefined)->toBeNull();
    expect(isset($request->undefined))->toBeFalse();
    expect($request->undefined)->toBeEmpty();

    // Special case: simulates empty QueryString and Routes, without the Route Resolver.
    // It'll happen when you try to get a parameter outside a route.
    $request = Request::create('/');

    // Parameter 'undefined' is undefined/null, then it NOT ISSET and is EMPTY.
    expect($request->undefined)->toBeNull();
    expect(isset($request->undefined))->toBeFalse();
    expect($request->undefined)->toBeEmpty();
});

test('magic methods prefer input over files for colliding keys', function () {
    $file = new SymfonyUploadedFile(__FILE__, 'email.txt');
    $request = Request::create('/', 'POST', ['email' => 'taylor@laravel.com'], [], ['email' => $file]);

    expect($request->email)->toBe('taylor@laravel.com');
    expect($request['email'])->toBe('taylor@laravel.com');
});

test('magic methods merge nested input and files while preferring input', function () {
    $avatar = new SymfonyUploadedFile(__FILE__, 'avatar.jpg');
    $collision = new SymfonyUploadedFile(__FILE__, 'name.txt');
    $request = Request::create('/', 'POST', [
        'profile' => ['name' => 'Taylor'],
    ], [], [
        'profile' => ['name' => $collision, 'avatar' => $avatar],
    ]);

    $expected = [
        'name' => 'Taylor',
        'avatar' => $request->file('profile.avatar'),
    ];

    expect($request->profile)->toBe($expected);
    expect($request->all())->toBe(['profile' => $expected]);
});

test('http request flash calls session flash input with input data', function () {
    $session = new Store('test', new NullSessionHandler);
    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'email' => 'foo']);
    $request->setMarinerSession($session);
    $request->flash();
    expect($session->getOldInput())->toBe(['name' => 'Taylor', 'email' => 'foo']);
    expect($session->get('_flash.new', []))->toContain('_old_input');
});

test('http request flash only calls flash with proper parameters', function () {
    $session = new Store('test', new NullSessionHandler);
    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'email' => 'foo']);
    $request->setMarinerSession($session);
    $request->flashOnly(['name']);
    expect($session->getOldInput())->toBe(['name' => 'Taylor']);
    expect($session->get('_flash.new', []))->toContain('_old_input');
});

test('http request flash except calls flash with proper parameters', function () {
    $session = new Store('test', new NullSessionHandler);
    $request = Request::create('/', 'GET', ['name' => 'Taylor', 'email' => 'foo']);
    $request->setMarinerSession($session);
    $request->flashExcept(['email']);
    expect($session->getOldInput())->toBe(['name' => 'Taylor']);
    expect($session->get('_flash.new', []))->toContain('_old_input');
});

test('generating json request from parent request uses correct type', function () {
    if (! method_exists(SymfonyRequest::class, 'getPayload')) {
        return;
    }

    $base = SymfonyRequest::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"hello":"world"}');

    $request = Request::createFromBase($base);

    expect($request->getPayload())->toBeInstanceOf(InputBag::class);
    expect($request->getPayload()->get('hello'))->toBe('world');
});

test('creating json request from base does not trigger request property deprecation', function () {
    $request = Request::createFromBase(
        SymfonyRequest::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"hello":"world"}')
    );

    expect($request->isJson())->toBeTrue();
    expect($request->input('hello'))->toBe('world');
});

test('json requests can merge data into json request', function () {
    if (! method_exists(SymfonyRequest::class, 'getPayload')) {
        return;
    }

    $base = SymfonyRequest::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"first":"Taylor","last":"Otwell"}');
    $request = Request::createFromBase($base);

    $request->merge([
        'name' => $request->get('first').' '.$request->get('last'),
    ]);

    expect($request->get('name'))->toBe('Taylor Otwell');
});

test('it can have objects in json payload', function () {
    if (! method_exists(SymfonyRequest::class, 'getPayload')) {
        return;
    }

    $base = SymfonyRequest::create('/', 'POST', server: ['CONTENT_TYPE' => 'application/json'], content: '{"framework":{"name":"Laravel"}}');
    $request = Request::createFromBase($base);

    $value = $request->get('framework');

    expect($request->get('framework'))->toBe(['name' => 'Laravel']);
});

test('it does not generate json errors for empty content', function () {
    // clear any existing errors
    json_encode(null);

    Request::create('', 'GET')->json();

    expect(json_last_error())->toBe(JSON_ERROR_NONE);
});

test('it clamps values', function () {
    $request = Request::create('/', 'GET', [
        'per_page' => 100,
        'float' => 9.24,
        'empty' => '',
        'null' => null,
        'string' => 'string',
    ]);

    expect($request->clamp('per_page', 100, 101))->toBe(100);
    expect($request->clamp('per_page', -10, 10))->toBe(10);
    expect($request->clamp('per_page_2', 25, 100, 1))->toBe(25);
    expect($request->clamp('per_page', 1, 250, 99))->toBe(100);
    expect($request->clamp('per_page', 1.11, 22.4, 2))->toBe(22.4);
    expect($request->clamp('float', 1, 10))->toBe(9.24);
    expect($request->clamp('empty', 10, 100, 15))->toBe(15);
    expect($request->clamp('string', 10, 100, 15))->toBe(15);
    expect($request->clamp('null', 10, 100, 15))->toBe(15);
});
