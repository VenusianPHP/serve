<?php

namespace Venusian\Serve\Tests\Integration\Validation;

use Mariner\Http\Request;
use Mariner\Validation\ValidationException;

test('validate macro', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor']);

    $validated = $request->validate(['name' => 'string']);

    expect($validated)->toBe(['name' => 'Taylor']);
});

test('validate macro when it fails', function () {

    $request = Request::create('/', 'GET', ['name' => null]);

    $request->validate(['name' => 'string']);
})->throws(ValidationException::class);

test('validate with bag macro', function () {
    $request = Request::create('/', 'GET', ['name' => 'Taylor']);

    $validated = $request->validateWithBag('some_bag', ['name' => 'string']);

    expect($validated)->toBe(['name' => 'Taylor']);
});

test('validate with bag macro when it fails', function () {
    $request = Request::create('/', 'GET', ['name' => null]);

    try {
        $request->validateWithBag('some_bag', ['name' => 'string']);
    } catch (ValidationException $validationException) {
        expect($validationException->errorBag)->toBe('some_bag');
    }
});
