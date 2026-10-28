<?php

use Mariner\Auth\Access\AuthorizationException;
use Mariner\Auth\AuthenticationException;
use Mariner\Contracts\Http\ExceptionRenderer;
use Mariner\Contracts\NutsAndBolts\Responsable;
use Mariner\Http\Exceptions\HttpResponseException;
use Mariner\Http\Exceptions\OriginMismatchException;
use Mariner\Http\Request;
use Mariner\Http\Response;
use Mariner\Routing\Exceptions\BackedEnumCaseNotFoundException;
use Mariner\Session\TokenMismatchException;
use Mariner\Validation\ValidationException;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\HttpFoundation\Exception\SuspiciousOperationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Contracts\Debug\ExceptionHandler;
use Voyager\Database\Instrument\ModelNotFoundException;
use Voyager\Database\RecordNotFoundException;
use Voyager\Database\RecordsNotFoundException;

/** An exception handler that records what it is asked to report, and can be told to throw. */
class RecordingExceptionHandler implements ExceptionHandler
{
    public array $reported = [];

    public bool $throws = false;

    public function report(Throwable $e): void
    {
        $this->reported[] = $e;

        if ($this->throws) {
            throw new LogicException('reporter down');
        }
    }

    public function shouldReport(Throwable $e): bool
    {
        return true;
    }

    public function renderForConsole(OutputInterface $output, Throwable $e): void {}
}

class ThrowingRouteMiddleware
{
    public function handle($request, $next)
    {
        throw new RuntimeException('from middleware');
    }
}

class SelfRenderingException extends Exception
{
    public function render(Request $request)
    {
        return new Response('rendered itself', 299);
    }
}

class ResponsableException extends Exception implements Responsable
{
    public function toResponse($request)
    {
        return new Response('responsable', 298);
    }
}

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->handler = new RecordingExceptionHandler;
    $this->app->registerInstance(ExceptionHandler::class, $this->handler);
    $this->renderer = $this->app->make(ExceptionRenderer::class);
    config(['app.debug' => false]);
});
afterEach(fn () => WebApp::tearDown($this));

/** A request in the current scope, handed to the URL generator, as the kernel does. */
function webRequest(string $uri = '/', string $method = 'GET', array $parameters = [], bool $json = false): Request
{
    $request = Request::create($uri, $method, $parameters, server: $json ? ['HTTP_ACCEPT' => 'application/json'] : []);
    $request->setMarinerSession(app('session.store'));
    app()->registerInstance('request', $request);
    app('url')->setRequest($request);

    return $request;
}

it('renders an exception thrown by route middleware, reporting it once', function () {
    $this->app['router']->get('/throws', fn () => 'unreached')->middleware(ThrowingRouteMiddleware::class);

    $response = $this->app['router']->dispatch(webRequest('/throws'));

    expect($response->getStatusCode())->toBe(500)
        ->and($this->handler->reported)->toHaveCount(1)
        ->and($this->handler->reported[0]->getMessage())->toBe('from middleware');
});

it('maps each exception to its status, as Laravel v13.37.0 does', function (Throwable $e, int $status) {
    expect($this->renderer->handle(webRequest(json: true), $e)->getStatusCode())->toBe($status);
})->with([
    'model not found' => [fn () => new ModelNotFoundException, 404],
    'record not found' => [fn () => new RecordNotFoundException, 404],
    'records not found' => [fn () => new RecordsNotFoundException, 404],
    'enum case not found' => [fn () => new BackedEnumCaseNotFoundException('Suit', 'Joker'), 404],
    'authorization' => [fn () => new AuthorizationException, 403],
    'authorization with status' => [fn () => (new AuthorizationException)->withStatus(404), 404],
    'origin mismatch' => [fn () => new OriginMismatchException, 403],
    'token mismatch' => [fn () => new TokenMismatchException, 419],
    'suspicious operation' => [fn () => new SuspiciousOperationException, 400],
    'http exception' => [fn () => new HttpException(418), 418],
    'anything else' => [fn () => new RuntimeException('secret'), 500],
]);

it('keeps a server error\'s message out of the response unless debugging', function () {
    $quiet = $this->renderer->handle(webRequest(json: true), new RuntimeException('secret'));
    config(['app.debug' => true]);
    $loud = $this->renderer->handle(webRequest(json: true), new RuntimeException('secret'));
    $page = $this->renderer->handle(webRequest(), new RuntimeException('secret'));
    config(['app.debug' => false]);
    $quiet_page = $this->renderer->handle(webRequest(), new RuntimeException('secret'));

    expect(json_decode($quiet->getContent(), true))->toBe(['message' => 'Server Error'])
        ->and(json_decode($loud->getContent(), true))->toMatchArray(['message' => 'secret', 'exception' => RuntimeException::class])
        ->and($page->getContent())->toContain('secret')
        ->and($quiet_page->getStatusCode())->toBe(500)
        ->and($quiet_page->getContent())->not->toContain('secret');
});

it('keeps an HTTP exception\'s status and headers', function () {
    $response = $this->renderer->handle(webRequest(), new HttpException(429, 'slow down', headers: ['Retry-After' => '30']));

    expect($response->getStatusCode())->toBe(429)
        ->and($response->headers->get('Retry-After'))->toBe('30');
});

it('renders the app\'s view for a status when it has one', function () {
    $response = $this->renderer->handle(webRequest(), new HttpException(418, 'short and stout'));

    expect($response->getStatusCode())->toBe(418)
        ->and($response->getContent())->toContain('teapot: short and stout');
});

it('answers an unauthenticated request with 401 JSON, a redirect to log in, or no content', function () {
    $json = $this->renderer->handle(webRequest(json: true), new AuthenticationException);
    $route = $this->app['router']->get('/account', fn () => 'account');
    $routed = webRequest('/account');
    $routed->setRouteResolver(fn () => $route);
    $redirect = $this->renderer->handle($routed, new AuthenticationException(redirectTo: '/login'));
    $bare = $this->renderer->handle(webRequest('/account'), new AuthenticationException);

    expect([$json->getStatusCode(), json_decode($json->getContent(), true)])->toBe([401, ['message' => 'Unauthenticated.']])
        ->and([$redirect->getStatusCode(), $redirect->headers->get('Location')])->toBe([302, 'http://localhost/login'])
        ->and(app('session.store')->get('url.intended'))->toBe('http://localhost/account')
        ->and([$bare->getStatusCode(), $bare->getContent()])->toBe([401, '']);
});

it('answers a validation failure with 422 JSON, or a redirect back with errors and input minus passwords', function () {
    $e = fn () => ValidationException::withMessages(['email' => ['The email is taken.']]);

    $json = $this->renderer->handle(webRequest(json: true), $e());

    $request = webRequest('/register', 'POST', ['email' => 'a@b.c', 'password' => 'hunter2']);
    $request->headers->set('referer', 'http://localhost/register');
    $redirect = $this->renderer->handle($request, $e());
    $session = app('session.store');

    expect($json->getStatusCode())->toBe(422)
        ->and(json_decode($json->getContent(), true)['errors'])->toBe(['email' => ['The email is taken.']])
        ->and([$redirect->getStatusCode(), $redirect->headers->get('Location')])->toBe([302, 'http://localhost/register'])
        ->and($session->get('errors')->getBag('default')->first('email'))->toBe('The email is taken.')
        ->and($session->get('_old_input'))->toBe(['email' => 'a@b.c']);
});

it('uses a response the exception carries or makes itself', function (Throwable $e, int $status) {
    expect($this->renderer->handle(webRequest(), $e)->getStatusCode())->toBe($status);
})->with([
    'http response exception' => [fn () => new HttpResponseException(new Response('carried', 297)), 297],
    'self-rendering' => [fn () => new SelfRenderingException, 299],
    'responsable' => [fn () => new ResponsableException, 298],
]);

it('reports once when a renderable callback handles the exception', function () {
    $this->renderer->renderable(fn (DomainException $e, Request $request) => new Response('handled', 409));

    $e = new DomainException('conflict');
    $first = $this->renderer->handle(webRequest(), $e);
    $this->renderer->handle(webRequest(), $e);

    expect([$first->getStatusCode(), $first->getContent()])->toBe([409, 'handled'])
        ->and($this->handler->reported)->toBe([$e]);
});

it('falls back to a plain 500 when rendering throws, reporting both once', function () {
    $this->renderer->renderable(fn (DomainException $e) => throw new LogicException('renderer broke'));

    $e = new DomainException('original');
    $response = $this->renderer->handle(webRequest(), $e);

    expect([$response->getStatusCode(), $response->getContent()])->toBe([500, 'Server Error'])
        ->and(array_map(fn ($r) => $r->getMessage(), $this->handler->reported))->toBe(['original', 'renderer broke']);
});

it('still answers when the reporter itself throws', function () {
    $this->handler->throws = true;
    $log = tempnam(sys_get_temp_dir(), 'renderer');
    $previous = ini_set('error_log', $log);

    try {
        $response = $this->renderer->handle(webRequest(json: true), new HttpException(418));
    } finally {
        ini_set('error_log', $previous);
    }

    expect($response->getStatusCode())->toBe(418)
        ->and(file_get_contents($log))->toContain('Mariner could not report')->toContain('reporter down');

    unlink($log);
});
