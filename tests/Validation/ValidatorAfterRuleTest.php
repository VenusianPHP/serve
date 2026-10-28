<?php

namespace Venusian\Serve\Tests\Validation;

use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Validator;

test('after accepts array of rules', function () {
    $validator = new Validator(new Translator(new ArrayLoader, 'en'), [], []);

    $validator->after([
        fn ($validator) => $validator->errors()->add('closure', 'true'),
        new InvokableAfterRule,
        new AfterMethodRule,
    ])->messages()->messages();

    expect($validator->messages()->messages())->toBe([
        'closure' => ['true'],
        'invokableAfterRule' => ['true'],
        'afterMethodRule' => ['true'],
    ]);
});

class InvokableAfterRule
{
    public function __invoke($validator)
    {
        $validator->errors()->add('invokableAfterRule', 'true');
    }
}

class AfterMethodRule
{
    public function __invoke()
    {
        //
    }

    public function after($validator)
    {
        $validator->errors()->add('afterMethodRule', 'true');
    }
}
