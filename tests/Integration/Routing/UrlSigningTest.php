<?php

namespace Venusian\Serve\Tests\Integration\Routing;

use Mariner\Contracts\Routing\UrlRoutable;
use Mariner\Http\Request;
use Mariner\Routing\Exceptions\InvalidSignatureException;
use Mariner\Routing\Middleware\ValidateSignature;
use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mariner\NutsAndBolts\Facades\Route;
use Mariner\NutsAndBolts\Facades\URL;
use Voyager\NutsAndBolts\Str;
use InvalidArgumentException;

beforeEach(function () {
    $app = $this->app;
    $app['config']->set(['app.key' => 'AckfSECXIvnK5r28GVIWUAxmbBSjTsmF']);
});

test('signing url', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature() ? 'valid' : 'invalid';
    })->name('foo');

    expect($url = URL::signedRoute('foo', ['id' => 1]))->toBeString();

    tap($this->get($url), function ($response) {
        expect($response->original)->toBe('valid');

        expect($response->baseRequest->query('signature'))->toBeString();
    });
});

test('signing url with custom route slug', function () {
    Route::get('/foo/{post:slug}', function (Request $request, $slug) {
        return ['slug' => $slug, 'valid' => $request->hasValidSignature() ? 'valid' : 'invalid'];
    })->name('foo');

    $model = new RoutableInterfaceStub;
    $model->routable = 'routable-slug';

    expect($url = URL::signedRoute('foo', ['post' => $model]))->toBeString();

    tap($this->get($url), function ($response) {
        expect($response->original['valid'])->toBe('valid');
        expect($response->original['slug'])->toBe('routable-slug');

        expect($response->baseRequest->route('post'))->toBe('routable-slug');
    });
});

test('signing url with percent sign in route slug', function () {
    Route::get('/foo/{post:slug}', function (Request $request, $slug) {
        return ['slug' => $slug, 'valid' => $request->hasValidSignature() ? 'valid' : 'invalid'];
    })->name('foo');

    $model = new RoutableInterfaceStub;
    $model->slug = '%66oo';

    // The percent sign has to be escaped in the generated URL. Otherwise the router
    // decodes "%66" back into an "f" when matching the URL and binds a different
    // model than the one the URL was generated for...
    expect(parse_url($url = URL::signedRoute('foo', ['post' => $model]), PHP_URL_PATH))->toBe('/foo/%2566oo');

    tap($this->get($url), function ($response) {
        expect($response->original['valid'])->toBe('valid');
        expect($response->original['slug'])->toBe('%66oo');

        expect($response->baseRequest->route('post'))->toBe('%66oo');
    });
});

test('temporary signed urls', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature() ? 'valid' : 'invalid';
    })->name('foo');

    Carbon::setTestNow(Carbon::create(2018, 1, 1));
    expect($url = URL::temporarySignedRoute('foo', Carbon::now()->addMinutes(5), ['id' => 1]))->toBeString();
    expect($this->get($url)->original)->toBe('valid');

    Carbon::setTestNow(Carbon::create(2018, 1, 1)->addMinutes(10));
    expect($this->get($url)->original)->toBe('invalid');
});

test('temporary signed urls with expires parameter', function () {

    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature() ? 'valid' : 'invalid';
    })->name('foo');

    URL::temporarySignedRoute('foo', Carbon::now()->addMinutes(5), ['id' => 1, 'expires' => 253402300799]);
})->throws(InvalidArgumentException::class, 'reserved');

test('signed url with url without signature parameter', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature() ? 'valid' : 'invalid';
    })->name('foo');

    expect($this->get('/foo/1')->original)->toBe('invalid');
});

test('signed url with null parameter', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature() ? 'valid' : 'invalid';
    })->name('foo');

    expect($url = URL::signedRoute('foo', ['id' => 1, 'param']))->toBeString();
    expect($this->get($url)->original)->toBe('valid');
});

test('signed url with empty string parameter', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature() ? 'valid' : 'invalid';
    })->name('foo');

    expect($url = URL::signedRoute('foo', ['id' => 1, 'param' => '']))->toBeString();
    expect($this->get($url)->original)->toBe('valid');
});

test('signed url with multiple parameters', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature() ? 'valid' : 'invalid';
    })->name('foo');

    expect($url = URL::signedRoute('foo', ['id' => 1, 'param1' => 'value1', 'param2' => 'value2']))->toBeString();
    expect($this->get($url)->original)->toBe('valid');
});

test('signed url with signature text in key or value', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature() ? 'valid' : 'invalid';
    })->name('foo');

    expect($url = URL::signedRoute('foo', ['id' => 1, 'custom-signature' => 'signature=value']))->toBeString();
    expect($this->get($url)->original)->toBe('valid');
});

test('signed url with appended null parameter invalid', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature() ? 'valid' : 'invalid';
    })->name('foo');

    expect($url = URL::signedRoute('foo', ['id' => 1]))->toBeString();
    expect($this->get($url.'&appended')->original)->toBe('invalid');
});

test('signed url prefers vapor raw query string over query string', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature() ? 'valid' : 'invalid';
    })->name('foo');

    expect($url = URL::signedRoute('foo', ['id' => 1]))->toBeString();
    $originalQueryString = Str::after($url, '?');

    $this->withServerVariables(['VAPOR_RAW_QUERY_STRING' => $originalQueryString]);

    expect($this->get($url.'&tampered=1')->original)->toBe('valid');
});

test('signed url is invalid when vapor raw query string does not match', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature() ? 'valid' : 'invalid';
    })->name('foo');

    expect($url = URL::signedRoute('foo', ['id' => 1, 'extra' => 'value']))->toBeString();

    $this->withServerVariables(['VAPOR_RAW_QUERY_STRING' => str_replace('extra=value', 'extra=tampered', Str::after($url, '?'))]);

    expect($this->get($url)->original)->toBe('invalid');
});

test('signed url falls back to query string when vapor raw query string is absent', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature() ? 'valid' : 'invalid';
    })->name('foo');

    expect($url = URL::signedRoute('foo', ['id' => 1]))->toBeString();

    expect($this->get($url)->original)->toBe('valid');
});

test('signed url parameters parsed correctly', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature()
            && (int) $id === 1
            && $request->has('paramEmpty')
            && $request->has('paramEmptyString')
            && $request->query('paramWithValue') === 'value'
            ? 'valid'
            : 'invalid';
    })->name('foo');

    expect($url = URL::signedRoute('foo', ['id' => 1,
        'paramEmpty',
        'paramEmptyString' => '',
        'paramWithValue' => 'value',
    ]))->toBeString();
    expect($this->get($url)->original)->toBe('valid');
});

test('excepted parameters can be added in any order', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignatureWhileIgnoring(['one', 'two', 'three']) ? 'valid' : 'invalid';
    })->name('foo');

    expect($url = URL::signedRoute('foo', ['id' => 1,
        'bar' => 'baz',
    ]))->toBeString();

    expect($this->get($url.'&one=value&two=another-value')->original)->toBe('valid');
    expect($this->get($url.'&two=value&one=&three')->original)->toBe('valid');
});

test('unusual excepted parameters works as expected', function () {
    $this->withoutExceptionHandling();
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignatureWhileIgnoring(['']) ? 'valid' : 'invalid';
    })->name('foo');

    expect($url = URL::signedRoute('foo', ['id' => 1,
        'bar' => 'baz',
    ]))->toBeString();

    expect($this->get($url)->original)->toBe('valid');

    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignatureWhileIgnoring(['*', '[a-z]+']) ? 'valid' : 'invalid';
    })->name('foo');

    expect($url = URL::signedRoute('foo', ['id' => 1,
        'bar' => 'baz',
    ]))->toBeString();

    expect($this->get($url.'&*=value&[a-z]+=value')->original)->toBe('valid');
});

test('excepted parameter can be aprefix or suffix of another parameter', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignatureWhileIgnoring(['pre', 'fix']) ? 'valid' : 'invalid';
    })->name('foo');

    expect($url = URL::signedRoute('foo', ['id' => 1,
        'prefix' => 'value',
        'suffix' => 'value',
    ]))->toBeString();

    expect($this->get($url.'&pre=fix&fix=suff')->original)->toBe('valid');
});

test('signed middleware', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature() ? 'valid' : 'invalid';
    })->name('foo')->middleware(ValidateSignature::class);

    Carbon::setTestNow(Carbon::create(2018, 1, 1));
    expect($url = URL::temporarySignedRoute('foo', Carbon::now()->addMinutes(5), ['id' => 1]))->toBeString();
    expect($this->get($url)->original)->toBe('valid');
});

test('signed middleware with invalid url', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature() ? 'valid' : 'invalid';
    })->name('foo')->middleware(ValidateSignature::class);

    Carbon::setTestNow(Carbon::create(2018, 1, 1));
    expect($url = URL::temporarySignedRoute('foo', Carbon::now()->addMinutes(5), ['id' => 1]))->toBeString();
    Carbon::setTestNow(Carbon::create(2018, 1, 1)->addMinutes(10));

    $response = $this->get($url);
    $response->assertStatus(403);
});

test('signed middleware with routable parameter', function () {
    $model = new RoutableInterfaceStub;
    $model->routable = 'routable';

    Route::get('/foo/{bar}', function (Request $request, $routable) {
        return $request->hasValidSignature() ? $routable : 'invalid';
    })->name('foo');

    expect($url = URL::signedRoute('foo', $model))->toBeString();
    expect($this->get($url)->original)->toBe('routable');
});

test('signed middleware with relative path', function () {
    Route::get('/foo/relative', function (Request $request) {
        return $request->hasValidSignature($absolute = false) ? 'valid' : 'invalid';
    })->name('foo')->middleware('signed:relative');

    expect($url = 'https://fake.test'.URL::signedRoute('foo', [], null, $absolute = false))->toBeString();
    expect($this->get($url)->original)->toBe('valid');

    $response = $this->get('/foo/relative');
    $response->assertStatus(403);
});

test('signed middleware ignoring parameter', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
    })->name('foo')->middleware('signed:relative');

    expect($url = URL::signedRoute('foo', ['id' => 1]).'&ignore=me')->toBeString();
    $request = Request::create($url);
    $middleware = createValidateSignatureMiddleware(['ignore']);

    try {
        $middleware->handle($request, function ($request) {
            expect($request->hasValidSignatureWhileIgnoring(['ignore']))->toBeTrue();
        });
    } catch (InvalidSignatureException $exception) {
        $this->fail($exception->getMessage());
    }
});

test('signed middleware ignoring parameter via arguments with relative', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
    })->name('foo')->middleware('signed:relative,ignore');

    expect('https://fake.test'.URL::signedRoute('foo', ['id' => 1, 'ignore' => 'me'], null, false))->toBeString();

    $response = $this->get('/foo/1');
    $response->assertStatus(403);
});

test('signed middleware can globally ignore parameters', function () {
    ValidateSignature::except(['globally_ignore']);

    Route::get('/foo/{id}', function (Request $request, $id) {
    })->name('foo')->middleware('signed:relative');

    expect($url = URL::signedRoute('foo', ['id' => 1]).'&globally_ignore=me')->toBeString();
    $request = Request::create($url);
    $middleware = createValidateSignatureMiddleware(['ignore']);

    try {
        $middleware->handle($request, function ($request) {
            expect($request->hasValidSignatureWhileIgnoring(['globally_ignore']))->toBeTrue();
        });
    } catch (InvalidSignatureException $exception) {
        $this->fail($exception->getMessage());
    }
});

test('signed middleware ignoring parameter via arguments without relative', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
    })->name('foo')->middleware('signed:ignore');

    expect($url = 'https://fake.test'.URL::signedRoute('foo', ['id' => 1, 'ignore' => 'me'], null, false))->toBeString();

    $response = $this->get('/foo/1');
    $response->assertStatus(403);
});

test('signed middleware ignoring parameter via class and arguments', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
    })->name('foo')->middleware(IgnoreParameterMiddleware::relative('test'));

    expect($url = 'https://fake.test'.URL::signedRoute('foo', ['id' => 1, 'ignore' => 'me', 'test' => 'bar'], null, false))->toBeString();

    $response = $this->get('/foo/1');
    $response->assertStatus(403);
});

test('it can generate middleware definition via static method', function () {
    $signature = (string) ValidateSignature::relative();
    expect($signature)->toBe('Mariner\Routing\Middleware\ValidateSignature:relative');

    $signature = (string) ValidateSignature::absolute();
    expect($signature)->toBe('Mariner\Routing\Middleware\ValidateSignature');

    $signature = (string) ValidateSignature::relative(['foo', 'bar']);
    expect($signature)->toBe('Mariner\Routing\Middleware\ValidateSignature:relative,foo,bar');

    $signature = (string) ValidateSignature::absolute(['foo', 'bar']);
    expect($signature)->toBe('Mariner\Routing\Middleware\ValidateSignature:foo,bar');
});

test('urls signed by previous app keys are valid when added as previous keys', function () {
    Route::get('/foo/{id}', function (Request $request, $id) {
        return $request->hasValidSignature() ? 'valid' : 'invalid';
    })->name('foo');

    config(['app.key' => 'oldest-key']);
    $oldestURL = URL::signedRoute('foo', ['id' => 1]);

    config(['app.key' => 'old-key']);
    $oldURL = URL::signedRoute('foo', ['id' => 1]);

    config(['app.key' => 'new-key']);
    $newUrl = URL::signedRoute('foo', ['id' => 1]);

    tap($this->get($oldestURL), fn ($response) => expect($response->original)->toBe('invalid'));
    tap($this->get($oldURL), fn ($response) => expect($response->original)->toBe('invalid'));
    tap($this->get($newUrl), fn ($response) => expect($response->original)->toBe('valid'));

    config(['app.previous_keys' => ['old-key', 'oldest-key']]);

    tap($this->get($oldestURL), fn ($response) => expect($response->original)->toBe('valid'));
    tap($this->get($oldURL), fn ($response) => expect($response->original)->toBe('valid'));
    tap($this->get($newUrl), fn ($response) => expect($response->original)->toBe('valid'));
});

function createValidateSignatureMiddleware(array $ignore)
{
    return new class($ignore) extends ValidateSignature
    {
        public function __construct(array $ignore)
        {
            $this->ignore = $ignore;
        }
    };
}

class RoutableInterfaceStub implements UrlRoutable
{
    public $key;
    public $routable;
    public $slug = 'routable-slug';

    public function getRouteKey()
    {
        return $this->{$this->getRouteKeyName()};
    }

    public function getRouteKeyName()
    {
        return 'routable';
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

class IgnoreParameterMiddleware extends ValidateSignature
{
    protected $ignore = ['ignore'];
}
