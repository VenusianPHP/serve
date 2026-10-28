<?php

use Mariner\Core\Http\Kernel;
use Mariner\Http\Request;
use Mariner\Validation\Attributes\ErrorBag;
use Mariner\Validation\Attributes\RedirectTo;
use Mariner\Validation\Attributes\StopOnFirstFailure;
use Mariner\Validation\FormRequest;
use Venusian\Serve\Tests\Fixtures\WebApp;

class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->input('title') !== 'forbidden';
    }

    public function rules(): array
    {
        return ['title' => 'required|string|max:10', 'body' => 'nullable|string'];
    }
}

#[StopOnFirstFailure]
#[ErrorBag('post')]
#[RedirectTo('/compose')]
class AttributedPostRequest extends FormRequest
{
    public function rules(): array
    {
        return ['title' => 'required', 'body' => 'required'];
    }
}

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->kernel = $this->app->make(Kernel::class);
    config(['session.driver' => 'array']);

    $this->app['router']->post('/posts/new', fn (StorePostRequest $request) => $request->validated());
    $this->app['router']->middleware('web')->withoutMiddleware(\Mariner\Http\Middleware\PreventRequestForgery::class)
        ->post('/posts/attributed', fn (AttributedPostRequest $request) => 'unreached');
});
afterEach(fn () => WebApp::tearDown($this));

function post(Kernel $kernel, string $uri, array $data, bool $json = true)
{
    $request = Request::create($uri, 'POST', $data, server: $json ? ['HTTP_ACCEPT' => 'application/json'] : ['HTTP_REFERER' => 'http://localhost/form']);
    $response = $kernel->handle($request);
    $kernel->terminate($request, $response);

    return $response;
}

it('hands the route only the validated input of the current request', function () {
    $response = post($this->kernel, '/posts/new', ['title' => 'Hello', 'body' => 'World', 'extra' => 'dropped']);

    expect($response->getStatusCode())->toBe(200)
        ->and(json_decode($response->getContent(), true))->toBe(['title' => 'Hello', 'body' => 'World']);
});

it('answers invalid input with 422 and the errors', function () {
    $response = post($this->kernel, '/posts/new', ['title' => str_repeat('x', 11)]);

    expect($response->getStatusCode())->toBe(422)
        ->and(json_decode($response->getContent(), true)['errors'])->toHaveKey('title');
});

it('refuses a request that is not authorized with 403', function () {
    expect(post($this->kernel, '/posts/new', ['title' => 'forbidden'])->getStatusCode())->toBe(403);
});

it('redirects, bags and stops as its attributes say', function () {
    $response = post($this->kernel, '/posts/attributed', [], json: false);
    $errors = $this->app['session.store']->get('errors');

    expect([$response->getStatusCode(), $response->headers->get('Location')])->toBe([302, 'http://localhost/compose'])
        ->and($errors->getBag('post')->keys())->toBe(['title']);
});
