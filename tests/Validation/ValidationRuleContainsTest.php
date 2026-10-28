<?php

namespace Venusian\Serve\Tests\Validation;

use Venusian\Serve\Tests\Validation\Fixtures\ArrayKeys;
use Venusian\Serve\Tests\Validation\Fixtures\ArrayKeysBacked;
use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Rule;
use Mariner\Validation\Validator;

require_once __DIR__.'/Fixtures/Enums.php';

test('it correctly formats astring version of the rule', function () {
    $rule = Rule::contains('Taylor');
    expect((string) $rule)->toBe('contains:"Taylor"');

    $rule = Rule::contains('Taylor', 'Abigail');
    expect((string) $rule)->toBe('contains:"Taylor","Abigail"');

    $rule = Rule::contains(['Taylor', 'Abigail']);
    expect((string) $rule)->toBe('contains:"Taylor","Abigail"');

    $rule = Rule::contains(collect(['Taylor', 'Abigail']));
    expect((string) $rule)->toBe('contains:"Taylor","Abigail"');

    $rule = Rule::contains([ArrayKeys::key_1, ArrayKeys::key_2]);
    expect((string) $rule)->toBe('contains:"key_1","key_2"');

    $rule = Rule::contains([ArrayKeysBacked::key_1, ArrayKeysBacked::key_2]);
    expect((string) $rule)->toBe('contains:"key_1","key_2"');

    $rule = Rule::contains(['Taylor', 'Taylor']);
    expect((string) $rule)->toBe('contains:"Taylor","Taylor"');

    $rule = Rule::contains([1, 2, 3]);
    expect((string) $rule)->toBe('contains:"1","2","3"');

    $rule = Rule::contains(['"foo"', '"bar"', '"baz"']);
    expect((string) $rule)->toBe('contains:"""foo""","""bar""","""baz"""');
});

test('contains validation', function () {
    $trans = new Translator(new ArrayLoader, 'en');

    // Test fails when value is string
    $v = new Validator($trans, ['roles' => 'admin'], ['roles' => Rule::contains('editor')]);
    expect($v->fails())->toBeTrue();

    // Test passes when array contains the value
    $v = new Validator($trans, ['roles' => ['admin', 'user']], ['roles' => Rule::contains('admin')]);
    expect($v->passes())->toBeTrue();

    // Test fails when array doesn't contain all the values
    $v = new Validator($trans, ['roles' => ['admin', 'user']], ['roles' => Rule::contains(['admin', 'editor'])]);
    expect($v->fails())->toBeTrue();

    // Test fails when array doesn't contain all the values (using multiple arguments)
    $v = new Validator($trans, ['roles' => ['admin', 'user']], ['roles' => Rule::contains('admin', 'editor')]);
    expect($v->fails())->toBeTrue();

    // Test passes when array contains all the values
    $v = new Validator($trans, ['roles' => ['admin', 'user', 'editor']], ['roles' => Rule::contains(['admin', 'editor'])]);
    expect($v->passes())->toBeTrue();

    // Test passes when array contains all the values (using multiple arguments)
    $v = new Validator($trans, ['roles' => ['admin', 'user', 'editor']], ['roles' => Rule::contains('admin', 'editor')]);
    expect($v->passes())->toBeTrue();

    // Test fails when array doesn't contain the value
    $v = new Validator($trans, ['roles' => ['admin', 'user']], ['roles' => Rule::contains('editor')]);
    expect($v->fails())->toBeTrue();

    // Test fails when array doesn't contain any of the values
    $v = new Validator($trans, ['roles' => ['admin', 'user']], ['roles' => Rule::contains(['editor', 'manager'])]);
    expect($v->fails())->toBeTrue();

    // Test with empty array
    $v = new Validator($trans, ['roles' => []], ['roles' => Rule::contains('admin')]);
    expect($v->fails())->toBeTrue();

    // Test with nullable field
    $v = new Validator($trans, ['roles' => null], ['roles' => ['nullable', Rule::contains('admin')]]);
    expect($v->passes())->toBeTrue();
});
