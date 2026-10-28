<?php

namespace Venusian\Serve\Tests\Validation;

use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Validator;

test('in array rule uses strict comparison', function (mixed $value, mixed $otherValue, bool $expectation) {
    $trans = new Translator(new ArrayLoader, 'en');

    $v = new Validator($trans, ['value' => $value, 'other' => [$otherValue]], ['value' => 'in_array:other.*']);

    expect($v->passes())->toBe($expectation);
})->with([
    ['0e123456789', '0', false],
    ['1e0', '1', false],
    [1, '1', false],
    ['1', 1, false],
    [1, 1, true],
    ['1', '1', true],
]);
