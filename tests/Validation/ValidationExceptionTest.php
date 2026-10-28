<?php

namespace Venusian\Serve\Tests\Validation;

use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\ValidationException;
use Mariner\Validation\Validator;

test('exception summarizes zero errors', function () {
    $exception = getException([], []);

    expect($exception->getMessage())->toBe('The given data was invalid.');
});

test('exception summarizes one error', function () {
    $exception = getException([], ['foo' => 'required']);

    expect($exception->getMessage())->toBe('validation.required');
});

test('exception summarizes two errors', function () {
    $exception = getException([], ['foo' => 'required', 'bar' => 'required']);

    expect($exception->getMessage())->toBe('validation.required (and 1 more error)');
});

test('exception summarizes three or more errors', function () {
    $exception = getException([], [
        'foo' => 'required',
        'bar' => 'required',
        'baz' => 'required',
    ]);

    expect($exception->getMessage())->toBe('validation.required (and 2 more errors)');
});

test('exception translated summarizes two errors', function () {
    $translator = exceptionGetTranslator('uk', [
        '*' => [
            '*' => [
                'uk' => [
                    '(and :count more error)' => '(та ще :count помилка)',
                    '(and :count more errors)' => '(та ще :count помилка)|(та ще :count помилки)|(та ще :count помилок)',
                ],
            ],
        ],
    ]);

    $exception = getException([], [
        'foo' => 'required',
        'bar' => 'required',
    ], $translator);

    expect($exception->getMessage())->toBe('validation.required (та ще 1 помилка)');
});

test('exception translated summarizes three or more errors', function () {
    $translator = exceptionGetTranslator('uk', [
        '*' => [
            '*' => [
                'uk' => [
                    '(and :count more error)' => '(та ще :count помилка)',
                    '(and :count more errors)' => '(та ще :count помилка)|(та ще :count помилки)|(та ще :count помилок)',
                ],
            ],
        ],
    ]);

    $exception = getException([], [
        'foo' => 'required',
        'bar' => 'required',
        'baz' => 'required',
    ], $translator);

    expect($exception->getMessage())->toBe('validation.required (та ще 2 помилки)');
});

test('exception translated summarizes five or more errors', function () {
    $translator = exceptionGetTranslator('uk', [
        '*' => [
            '*' => [
                'uk' => [
                    '(and :count more error)' => '(та ще :count помилка)',
                    '(and :count more errors)' => '(та ще :count помилка)|(та ще :count помилки)|(та ще :count помилок)',
                ],
            ],
        ],
    ]);

    $exception = getException([], [
        'foo' => 'required',
        'bar' => 'required',
        'baz' => 'required',
        'baq' => 'required',
        'baw' => 'required',
        'bae' => 'required',
    ], $translator);

    expect($exception->getMessage())->toBe('validation.required (та ще 5 помилок)');
});

test('exception error zero errors', function () {
    $exception = getException([], []);

    expect($exception->errors())->toBe([]);
});

test('exception error one error', function () {
    $exception = getException([], ['foo' => 'required']);

    expect($exception->errors())->toBe(['foo' => ['validation.required']]);
});

test('exception status one error', function () {
    $exception = getException([], ['foo' => 'required']);
    $exception->status(500);

    expect($exception->status)->toEqual(500);
});

test('exception error bag one error', function () {
    $exception = getException([], ['foo' => 'required']);
    $exception->errorBag('milwad');

    expect($exception->errorBag)->toBe('milwad');
});

test('exception redirect to one error', function () {
    $exception = getException([], ['foo' => 'required']);
    $exception->redirectTo('https://google.com');

    expect($exception->redirectTo)->toBe('https://google.com');
});

test('exception get response one error', function () {
    $exception = getException([], ['foo' => 'required']);

    expect($exception->getResponse())->toBeNull();
});

test('get exception class from validator', function () {
    $validator = getValidator();

    $exception = $validator->getException();

    expect($exception)->toEqual(ValidationException::class);
});

function getException($data = [], $rules = [], $translator = null)
{
    $validator = getValidator($data, $rules, $translator);

    return new ValidationException($validator);
}

function getValidator($data = [], $rules = [], $translator = null)
{
    $translator ??= exceptionGetTranslator();

    return new Validator($translator, $data, $rules);
}

function exceptionGetTranslator($locale = 'en', $loaded = [])
{
    $translator ??= new Translator(new ArrayLoader, $locale);

    $translator->setLoaded($loaded);

    return $translator;
}
