<?php

namespace Venusian\Serve\Tests\Integration\Validation\Rules;

use Mariner\NutsAndBolts\Facades\Validator;

test('it can validate regardless of timezone', function (string $timezone) {
    date_default_timezone_set($timezone);

    $payload = ['date' => '2025-03-30 02:00:00'];
    $rules = ['date' => 'date_format:Y-m-d H:i:s'];

    $validator = Validator::make($payload, $rules);

    expect($validator->passes())->toBeTrue();
    expect($validator->errors()->all())->toBeEmpty();
})->with([
    ['UTC'],
    ['Europe/Amsterdam'],
]);
