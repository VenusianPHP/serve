<?php

namespace Venusian\Serve\Tests\Integration\Validation\Rules;

use Mariner\NutsAndBolts\Facades\Validator;
use Mariner\Validation\Rules\Email;

test('it can validate attribute as array', function (string $attribute) {
    $validator = Validator::make([
        'emails' => [
            $attribute => 'taylor@laravel.com',
        ],
    ], [
        'emails.*' => ['required', Email::default()->rfcCompliant()],
    ]);

    expect($validator->passes())->toBeTrue();
})->with([
    ['0'],
    ['.'],
    ['*'],
    ['__asterisk__'],
]);

test('it can validate attribute as array when validation should fails', function (string $attribute) {
    $validator = Validator::make([
        'emails' => [
            $attribute => 'taylor[at]laravel.com',
        ],
    ], [
        'emails.*' => ['required', Email::default()->rfcCompliant()],
    ]);

    expect($validator->passes())->toBeFalse();

    expect($validator->messages()->all())->toBe([
        0 => __('validation.email', ['attribute' => sprintf('emails.%s', str_replace('_', ' ', $attribute))]),
    ]);
})->with([
    ['0'],
    ['.'],
    ['*'],
    ['__asterisk__'],
]);
