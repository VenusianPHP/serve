<?php

use Mariner\Core\Http\Kernel;
use Mariner\Http\Events\RequestHandled;
use Mariner\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;
use Venusian\Serve\Tests\Fixtures\WebApp;

use function Voyager\NutsAndBolts\defer;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->kernel = $this->app->make(Kernel::class);
    $this->sessions = $this->app->storagePath('framework/sessions');
    config(['session.driver' => 'file']);
});

afterEach(function () {
    foreach (glob($this->sessions.'/*') as $file) {
        unlink($file);
    }

    WebApp::tearDown($this);
});

/** Handle a request through the kernel, then terminate it, as the front controller does. */
function serve(Kernel $kernel, Request $request)
{
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
}

/** The cookies a response set, by name. */
function cookiesOf($response): array
{
    $cookies = [];

    foreach ($response->headers->getCookies() as $cookie) {
        $cookies[$cookie->getName()] = $cookie->getValue();
    }

    return $cookies;
}

it('is one kernel for the whole app', function () {
    expect($this->app->make(Kernel::class))->toBe($this->kernel);
});

it('runs a request through the global middleware to its route', function () {
    $this->app['router']->post('/echo', fn (Request $request) => $request->all());

    $response = serve($this->kernel, Request::create('/echo', 'POST', ['name' => '  Ada  ', 'note' => '']));

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getContent(), true))->toBe(['name' => 'Ada', 'note' => null]);
});

it('generates URLs from the request it is handling, even with a generator built before it', function () {
    $this->app['router']->get('/where', fn () => url()->current());
    url()->current();

    $response = serve($this->kernel, Request::create('http://example.test/where'));

    expect($response->getContent())->toBe('http://example.test/where');
});

it('refuses a web POST without a CSRF token, and accepts one carrying the token it was given', function () {
    $this->app['router']->middleware('web')->get('/form', fn () => 'form');

    $refused = serve($this->kernel, Request::create('/posts', 'POST'));

    $form = serve($this->kernel, Request::create('/form'));
    $cookies = cookiesOf($form);
    $accepted = serve($this->kernel, Request::create('/posts', 'POST', cookies: $cookies, server: ['HTTP_X_XSRF_TOKEN' => $cookies['XSRF-TOKEN']]));

    $forged = serve($this->kernel, Request::create('/posts', 'POST', cookies: $cookies, server: ['HTTP_X_CSRF_TOKEN' => 'forged']));

    expect($refused->getStatusCode())->toBe(419)
        ->and([$form->getStatusCode(), $form->getContent()])->toBe([200, 'form'])
        ->and($cookies)->toHaveKey('XSRF-TOKEN')
        ->and([$accepted->getStatusCode(), $accepted->getContent()])->toBe([200, 'stored'])
        ->and($forged->getStatusCode())->toBe(419);
});

it('announces each request once, after it is handled', function () {
    $heard = [];
    $this->app['signals']->listen(RequestHandled::class, function (RequestHandled $event) use (&$heard) {
        $heard[] = $event->response->getContent();
    });

    $request = Request::create('/');
    $response = $this->kernel->handle($request);
    $before = $heard;
    $this->kernel->terminate($request, $response);

    expect($before)->toBe([])->and($heard)->toBe(['home']);
});

it('runs deferred callbacks once the response is sent, unless it failed', function () {
    $ran = [];
    $this->app['router']->get('/defers/{status}', function (int $status) use (&$ran) {
        defer(function () use (&$ran, $status) { $ran[] = $status; });
        defer(function () use (&$ran, $status) { $ran[] = "always {$status}"; })->always();

        return response('', $status);
    });

    serve($this->kernel, Request::create('/defers/200'));
    serve($this->kernel, Request::create('/defers/500'));

    expect($ran)->toBe([200, 'always 200', 'always 500']);
});

it('answers an exception from the route with a rendered response', function () {
    $this->app['router']->get('/fails', fn () => throw new RuntimeException('nope'));

    expect(serve($this->kernel, Request::create('/fails'))->getStatusCode())->toBe(500)
        ->and(serve($this->kernel, Request::create('/nowhere'))->getStatusCode())->toBe(404);
});

it('knows when a request started while it is in flight', function () {
    $this->app['router']->get('/started', fn (Request $request) => $this->kernel->requestStartedAt($request) !== null ? 'yes' : 'no');
    $slow = [];
    $this->kernel->whenRequestLifecycleIsLongerThan(-1, function ($started_at, $request) use (&$slow) {
        $slow[] = $request->path();
    });

    $request = Request::create('/started');
    $response = serve($this->kernel, $request);

    expect($response->getContent())->toBe('yes')
        ->and($this->kernel->requestStartedAt($request))->toBeNull()
        ->and($slow)->toBe(['started']);
});

it('dispatches to the app\'s routes from a kernel made before the app booted, as public/index.php makes it', function () {
    WebApp::tearDown($this);
    $app = require dirname(__DIR__).'/Fixtures/app/bootstrap/app.php';
    $kernel = $app->make(Kernel::class);

    $response = $kernel->handle(Request::create('/'));

    expect($response->getStatusCode())->toBe(200)->and($response->getContent())->toBe('home');
});
