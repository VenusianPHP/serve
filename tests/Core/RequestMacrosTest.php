<?php

use Mariner\Http\Request;
use Mariner\Validation\ValidationException;
use Venusian\Serve\Tests\Fixtures\WebApp;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->router = $this->app['router'];

    $this->router->get('download/{file}', fn (string $file) => "got {$file}")->name('download')->middleware('signed');
    $this->router->get('relative/{file}', fn (string $file) => "got {$file}")->name('relative')->middleware('signed:relative');
    $this->router->get('tracked/{file}', fn (string $file) => "got {$file}")->name('tracked')->middleware('signed:utm_source');
    $this->router->getRoutes()->refreshNameLookups();
});

afterEach(fn () => WebApp::tearDown($this));

/** Sends a GET for the URL through the HTTP kernel, which gives the router its middleware aliases. */
function signedGet($app, string $url)
{
    $kernel = $app->make(Mariner\Core\Http\Kernel::class);
    $response = $kernel->handle($request = Request::create($url));
    $kernel->terminate($request, $response);

    return $response;
}

it('validates a request with validate() and validateWithBag()', function () {
    $request = Request::create('/', 'POST', ['name' => 'ada', 'extra' => 'x']);

    expect($request->validate(['name' => 'required']))->toBe(['name' => 'ada']);

    try {
        $request->validateWithBag('profile', ['missing' => 'required']);
        $this->fail('validateWithBag() passed.');
    } catch (ValidationException $e) {
        expect($e->errorBag)->toBe('profile')
            ->and(array_keys($e->errors()))->toBe(['missing']);
    }
});

it('passes a valid signed URL and refuses a tampered one', function () {
    $url = $this->app['url']->signedRoute('download', ['file' => 'report.pdf']);

    expect(signedGet($this->app, $url)->getContent())->toBe('got report.pdf')
        ->and(signedGet($this->app, str_replace('report.pdf', 'secrets.pdf', $url))->getStatusCode())->toBe(403)
        ->and(signedGet($this->app, $url.'x')->getStatusCode())->toBe(403);
});

it('refuses an expired temporary signed URL', function () {
    $url = $this->app['url']->temporarySignedRoute('download', now()->addMinute(), ['file' => 'report.pdf']);

    expect(signedGet($this->app, $url)->getStatusCode())->toBe(200);

    Carbon\Carbon::setTestNow(now()->addMinutes(2));

    expect(signedGet($this->app, $url)->getStatusCode())->toBe(403);
});

it('checks relative signatures without the host', function () {
    $url = $this->app['url']->signedRoute('relative', ['file' => 'report.pdf'], absolute: false);

    expect(signedGet($this->app, 'https://elsewhere.test'.$url)->getContent())->toBe('got report.pdf');

    $request = Request::create('https://elsewhere.test'.$url);
    expect($request->hasValidRelativeSignature())->toBeTrue()
        ->and($request->hasValidSignature())->toBeFalse();
});

it('ignores the named query parameters when checking the signature', function () {
    $url = $this->app['url']->signedRoute('tracked', ['file' => 'report.pdf']);

    expect(signedGet($this->app, $url.'&utm_source=newsletter')->getContent())->toBe('got report.pdf')
        ->and(signedGet($this->app, $url.'&other=1')->getStatusCode())->toBe(403);

    $request = Request::create($url.'&utm_source=newsletter');
    expect($request->hasValidSignatureWhileIgnoring(['utm_source']))->toBeTrue()
        ->and($request->hasValidSignature())->toBeFalse();
});

it('collects logged exceptions only under unit tests', function () {
    expect($this->app->isBound(Mariner\Testing\LoggedExceptionCollection::class))->toBeFalse();
    WebApp::tearDown($this);

    putenv('APP_ENV=testing');

    try {
        $this->app = WebApp::boot();
        $this->app['signals']->dispatch(new Voyager\Log\Signals\MessageLogged('error', 'boom', ['exception' => $e = new RuntimeException('boom')]));

        expect($this->app->make(Mariner\Testing\LoggedExceptionCollection::class)->all())->toBe([$e]);
    } finally {
        putenv('APP_ENV');
    }
});
