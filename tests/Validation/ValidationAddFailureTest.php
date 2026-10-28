<?php

namespace Venusian\Serve\Tests\Validation;

use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Validator;

function makeValidator()
{
    $trans = new Translator(new ArrayLoader, 'en');

    return new Validator($trans, ['foo' => ['bar' => ['baz' => '']]], ['foo.bar.baz' => 'sometimes|required']);
}

test('add failure exists', function () {
    $validator = makeValidator();
    $method_name = 'addFailure';
    expect(method_exists($validator, $method_name))->toBeTrue();
    expect([$validator, $method_name])->toBeCallable();
});

test('add failure is functional', function () {
    $attribute = 'Eugene';
    $validator = makeValidator();
    $validator->addFailure($attribute, 'not_in');
    $messages = json_decode($validator->messages());
    expect($messages->{'foo.bar.baz'}[0])->toBe('validation.required');
    expect($messages->{$attribute}[0])->toBe('validation.not_in');
});
