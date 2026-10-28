<?php

namespace Venusian\Serve\Tests\Integration\Validation\Rules;

use Mariner\NutsAndBolts\Facades\Validator;
use Mariner\Validation\Rules\Password;

test('it can validate attribute as array', function (string $attribute) {
    $validator = Validator::make([
        'passwords' => [
            $attribute => 'secret',
        ],
    ], [
        'passwords.*' => ['required', Password::default()->min(6)],
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
        'passwords' => [
            $attribute => 'secret',
        ],
    ], [
        'passwords.*' => ['required', Password::default()->min(8)],
    ]);

    expect($validator->passes())->toBeFalse();

    expect($validator->messages()->all())->toBe([
        0 => sprintf('The passwords.%s field must be at least 8 characters.', str_replace('_', ' ', $attribute)),
    ]);
})->with([
    ['0'],
    ['.'],
    ['*'],
    ['__asterisk__'],
]);
