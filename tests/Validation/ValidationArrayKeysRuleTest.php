<?php

namespace Venusian\Serve\Tests\Validation;

use Venusian\Serve\Tests\Validation\Fixtures\ArrayKeys;
use Venusian\Serve\Tests\Validation\Fixtures\ArrayKeysBacked;
use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Rule;
use Mariner\Validation\Validator;
use InvalidArgumentException;

require_once __DIR__.'/Fixtures/Enums.php';

test('it correctly formats astring version of the rule', function () {
    $rule = Rule::arrayKeys('key_1', 'key_2', 'key_3');

    expect((string) $rule)->toBe('array_keys:key_1,key_2,key_3');

    $rule = Rule::arrayKeys(['key_1', 'key_2', 'key_3']);

    expect((string) $rule)->toBe('array_keys:key_1,key_2,key_3');

    $rule = Rule::arrayKeys(collect(['key_1', 'key_2', 'key_3']));

    expect((string) $rule)->toBe('array_keys:key_1,key_2,key_3');

    $rule = Rule::arrayKeys([ArrayKeys::key_1, ArrayKeys::key_2, ArrayKeys::key_3]);

    expect((string) $rule)->toBe('array_keys:key_1,key_2,key_3');

    $rule = Rule::arrayKeys([ArrayKeysBacked::key_1, ArrayKeysBacked::key_2, ArrayKeysBacked::key_3]);

    expect((string) $rule)->toBe('array_keys:key_1,key_2,key_3');

    $rule = Rule::arrayKeys([1, 2, 3]);

    expect((string) $rule)->toBe('array_keys:1,2,3');
});

test('array keys validation', function () {
    $trans = new Translator(new ArrayLoader, 'en');

    $v = new Validator($trans, ['foo' => ['key_1' => 'bar', 'key_3' => 'baz']], ['foo' => Rule::arrayKeys(['key_1', 'key_2'])]);
    expect($v->fails())->toBeTrue();

    $v = new Validator($trans, ['foo' => ['bar', 'baz']], ['foo' => Rule::arrayKeys(['key_1'])]);
    expect($v->fails())->toBeTrue();

    $v = new Validator($trans, ['foo' => 'not an array'], ['foo' => Rule::arrayKeys(['key_1'])]);
    expect($v->fails())->toBeTrue();

    $v = new Validator($trans, ['foo' => (object) ['key_1' => 'bar']], ['foo' => Rule::arrayKeys(['key_1'])]);
    expect($v->fails())->toBeTrue();

    $v = new Validator($trans, ['foo' => ['key_1' => 'bar', 'key_2' => '']], ['foo' => Rule::arrayKeys(['key_1', 'key_2'])]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['foo' => ['key_1' => 'bar']], ['foo' => Rule::arrayKeys(['key_1', 'key_2'])]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['foo' => ['key_1' => []]], ['foo' => Rule::arrayKeys(['key_1'])]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['foo' => []], ['foo' => Rule::arrayKeys(['key_1', 'key_2'])]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['foo' => ['bar', 'baz']], ['foo' => Rule::arrayKeys([0, 1])]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['foo' => ['key_1' => 'bar']], ['foo' => (string) Rule::arrayKeys(['key_1'])]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['foo' => null], ['foo' => ['nullable', Rule::arrayKeys(['key_1'])]]);
    expect($v->passes())->toBeTrue();
});

test('array keys validation requires at least one key', function () {
    $trans = new Translator(new ArrayLoader, 'en');

    $v = new Validator($trans, ['foo' => ['key_1' => 'bar']], ['foo' => 'array_keys']);

    $v->passes();
})->throws(InvalidArgumentException::class, 'Validation rule array_keys requires at least 1 parameters.');

test('array keys validation error message', function () {
    $trans = new Translator(new ArrayLoader, 'en');

    $trans->addLines([
        'validation.array_keys' => 'The :attribute field must only contain the following keys: :values.',
    ], 'en');

    $v = new Validator($trans, ['foo' => ['key_1' => 'bar', 'key_3' => 'baz']], ['foo' => Rule::arrayKeys(['key_1', 'key_2'])]);

    expect($v->fails())->toBeTrue();
    expect($v->messages()->first('foo'))->toBe('The foo field must only contain the following keys: key_1, key_2.');
    expect($v->failed()['foo'])->toBe(['ArrayKeys' => ['key_1', 'key_2']]);
});

test('array keys validation error message can reference the unexpected keys', function () {
    $trans = new Translator(new ArrayLoader, 'en');

    $v = new Validator(
        $trans,
        ['foo' => ['key_3' => 'bar', 'key_1' => 'baz', 'key_4' => 'qux']],
        ['foo' => Rule::arrayKeys(['key_1', 'key_2'])],
        ['foo.array_keys' => 'The :attribute field does not accept :unexpected. Accepted keys: :values.']
    );

    expect($v->fails())->toBeTrue();
    expect($v->messages()->first('foo'))->toBe('The foo field does not accept key_3, key_4. Accepted keys: key_1, key_2.');
});

test('unexpected keys are placeholder safe and empty for non arrays', function () {
    $trans = new Translator(new ArrayLoader, 'en');

    $v = new Validator(
        $trans,
        ['foo' => ['key_1' => 'a', ':values' => 'b', ':attribute' => 'c']],
        ['foo' => Rule::arrayKeys(['key_1'])],
        ['foo.array_keys' => 'Unexpected keys: :unexpected. Accepted: :values.']
    );

    expect($v->fails())->toBeTrue();
    expect($v->messages()->first('foo'))->toBe('Unexpected keys: :values, :attribute. Accepted: key_1.');

    $v = new Validator(
        $trans,
        ['foo' => 'not an array'],
        ['foo' => Rule::arrayKeys(['key_1'])],
        ['foo.array_keys' => 'Unexpected keys: :unexpected.']
    );

    expect($v->fails())->toBeTrue();
    expect($v->messages()->first('foo'))->toBe('Unexpected keys: .');
});
