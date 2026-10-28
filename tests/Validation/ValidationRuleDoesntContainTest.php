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
    $rule = Rule::doesntContain('Taylor');
    expect((string) $rule)->toBe('doesnt_contain:"Taylor"');

    $rule = Rule::doesntContain('Taylor', 'Abigail');
    expect((string) $rule)->toBe('doesnt_contain:"Taylor","Abigail"');

    $rule = Rule::doesntContain(['Taylor', 'Abigail']);
    expect((string) $rule)->toBe('doesnt_contain:"Taylor","Abigail"');

    $rule = Rule::doesntContain(collect(['Taylor', 'Abigail']));
    expect((string) $rule)->toBe('doesnt_contain:"Taylor","Abigail"');

    $rule = Rule::doesntContain([ArrayKeys::key_1, ArrayKeys::key_2]);
    expect((string) $rule)->toBe('doesnt_contain:"key_1","key_2"');

    $rule = Rule::doesntContain([ArrayKeysBacked::key_1, ArrayKeysBacked::key_2]);
    expect((string) $rule)->toBe('doesnt_contain:"key_1","key_2"');

    $rule = Rule::doesntContain(['Taylor', 'Taylor']);
    expect((string) $rule)->toBe('doesnt_contain:"Taylor","Taylor"');

    $rule = Rule::doesntContain([1, 2, 3]);
    expect((string) $rule)->toBe('doesnt_contain:"1","2","3"');

    $rule = Rule::doesntContain(['"foo"', '"bar"', '"baz"']);
    expect((string) $rule)->toBe('doesnt_contain:"""foo""","""bar""","""baz"""');
});

test('doesnt contain validation', function () {
    $trans = new Translator(new ArrayLoader, 'en');

    // Test fails when value is string
    $v = new Validator($trans, ['roles' => 'admin'], ['roles' => Rule::doesntContain('admin')]);
    expect($v->fails())->toBeTrue();

    // Test fails when array contains the value
    $v = new Validator($trans, ['roles' => ['admin', 'user']], ['roles' => Rule::doesntContain('admin')]);
    expect($v->fails())->toBeTrue();

    // Test fails when array contains all the values (using array argument)
    $v = new Validator($trans, ['roles' => ['admin', 'user']], ['roles' => Rule::doesntContain(['admin', 'editor'])]);
    expect($v->fails())->toBeTrue();

    // Test fails when array contains some of the values (using multiple arguments)
    $v = new Validator($trans, ['roles' => ['admin', 'user']], ['roles' => Rule::doesntContain('subscriber', 'admin')]);
    expect($v->fails())->toBeTrue();

    // Test passes when array does not contain any value
    $v = new Validator($trans, ['roles' => ['subscriber', 'guest']], ['roles' => Rule::doesntContain(['admin', 'editor'])]);
    expect($v->passes())->toBeTrue();

    // Test fails when array includes a value (using string-like format)
    $v = new Validator($trans, ['roles' => ['admin', 'user']], ['roles' => 'doesnt_contain:admin']);
    expect($v->fails())->toBeTrue();

    // Test passes when array doesn't include a value (using string-like format)
    $v = new Validator($trans, ['roles' => ['admin', 'user']], ['roles' => 'doesnt_contain:editor']);
    expect($v->passes())->toBeTrue();

    // Test fails when array doesn't contain the value
    $v = new Validator($trans, ['roles' => ['admin', 'user']], ['roles' => Rule::doesntContain('admin')]);
    expect($v->fails())->toBeTrue();

    // Test with empty array
    $v = new Validator($trans, ['roles' => []], ['roles' => Rule::doesntContain('admin')]);
    expect($v->passes())->toBeTrue();

    // Test with nullable field
    $v = new Validator($trans, ['roles' => null], ['roles' => ['nullable', Rule::doesntContain('admin')]]);
    expect($v->passes())->toBeTrue();
});

test('doesnt contain rule does not use loose comparisons', function (array $value, bool $expectation) {
    $trans = new Translator(new ArrayLoader, 'en');

    $v = new Validator($trans, ['x' => $value], ['x' => ['doesnt_contain:0']]);

    expect($v->passes())->toBe($expectation);
})->with([
    [['0e123'], true],
    [['0'], false],
]);
