<?php

use Mariner\Http\Request;
use Mariner\Validation\ValidatesRequests;
use Mariner\Validation\ValidationException;
use Venusian\Serve\Tests\Fixtures\WebApp;

class ValidatingController
{
    use ValidatesRequests;
}

beforeEach(fn () => $this->app = WebApp::boot());
afterEach(fn () => WebApp::tearDown($this));

it('returns only the validated input', function () {
    $request = Request::create('/', 'POST', ['name' => 'Ada', 'extra' => 'dropped']);

    expect((new ValidatingController)->validate($request, ['name' => 'required']))->toBe(['name' => 'Ada']);
});

it('throws a validation exception for invalid input', function () {
    $request = Request::create('/', 'POST', []);

    expect(fn () => (new ValidatingController)->validate($request, ['name' => 'required']))
        ->toThrow(ValidationException::class);
});

it('validates the current request with rules or a validator', function () {
    $this->app->registerInstance('request', Request::create('/', 'POST', ['age' => '30']));
    $controller = new ValidatingController;

    expect($controller->validateWith(['age' => 'integer']))->toBe(['age' => '30'])
        ->and($controller->validateWith(validator(['n' => 1], ['n' => 'integer'])))->toBe(['n' => 1]);
});

it('puts the errors in the named bag', function () {
    $request = Request::create('/', 'POST', []);

    try {
        (new ValidatingController)->validateWithBag('login', $request, ['email' => 'required']);
        $this->fail('validation passed');
    } catch (ValidationException $e) {
        expect($e->errorBag)->toBe('login')->and($e->errors())->toHaveKey('email');
    }
});
