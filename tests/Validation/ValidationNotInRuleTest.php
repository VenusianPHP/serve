<?php

namespace Venusian\Serve\Tests\Validation;

use Venusian\Serve\Tests\Validation\Fixtures\IntegerStatus;
use Venusian\Serve\Tests\Validation\Fixtures\PureEnum;
use Venusian\Serve\Tests\Validation\Fixtures\StringStatus;
use Venusian\Serve\Tests\Validation\Fixtures\Values;
use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Rule;
use Mariner\Validation\Rules\NotIn;
use Mariner\Validation\Validator;

require_once __DIR__.'/Fixtures/Enums.php';
require_once __DIR__.'/Fixtures/Values.php';

test('it correctly formats astring version of the rule', function () {
    $rule = new NotIn(['Laravel', 'Framework', 'PHP']);

    expect((string) $rule)->toBe('not_in:"Laravel","Framework","PHP"');

    $rule = new NotIn(collect(['Taylor', 'Michael', 'Tim']));

    expect((string) $rule)->toBe('not_in:"Taylor","Michael","Tim"');

    $rule = Rule::notIn(collect([1, 2, 3, 4]));

    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = Rule::notIn(collect([1, 2, 3, 4]));

    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = Rule::notIn([1, 2, 3, 4]);

    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = Rule::notIn(collect([1, 2, 3, 4]));

    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = Rule::notIn(new Values);

    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = new NotIn(new Values);

    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = Rule::notIn('1', '2', '3', '4');

    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = new NotIn('1', '2', '3', '4');

    expect((string) $rule)->toBe('not_in:"1","2","3","4"');

    $rule = Rule::notIn([StringStatus::done]);

    expect((string) $rule)->toBe('not_in:"done"');

    $rule = Rule::notIn([IntegerStatus::done]);

    expect((string) $rule)->toBe('not_in:"2"');

    $rule = Rule::notIn([PureEnum::one]);

    expect((string) $rule)->toBe('not_in:"one"');
});

test('not in rule validation', function () {
    $trans = new Translator(new ArrayLoader, 'en');

    $v = new Validator($trans, ['x' => 'foo'], ['x' => Rule::notIn('bar', 'baz')]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['x' => 'foo'], ['x' => (string) Rule::notIn('bar', 'baz')]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['x' => 'foo'], ['x' => [Rule::notIn('foo', 'bar')]]);
    expect($v->passes())->toBeFalse();

    $v = new Validator($trans, ['x' => 'foo'], ['x' => ['required', Rule::notIn('bar', 'baz')]]);
    expect($v->passes())->toBeTrue();
});
