<?php

namespace Venusian\Serve\Tests\Validation;

use Venusian\Serve\Tests\Validation\Fixtures\IntegerStatus;
use Venusian\Serve\Tests\Validation\Fixtures\PureEnum;
use Venusian\Serve\Tests\Validation\Fixtures\StringStatus;
use Venusian\Serve\Tests\Validation\Fixtures\Values;
use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Rule;
use Mariner\Validation\Rules\In;
use Mariner\Validation\Validator;

require_once __DIR__.'/Fixtures/Enums.php';
require_once __DIR__.'/Fixtures/Values.php';

test('it correctly formats astring version of the rule', function () {
    $rule = new In(['Laravel', 'Framework', 'PHP']);

    expect((string) $rule)->toBe('in:"Laravel","Framework","PHP"');

    $rule = new In(collect(['Taylor', 'Michael', 'Tim']));

    expect((string) $rule)->toBe('in:"Taylor","Michael","Tim"');

    $rule = new In(['Life, the Universe and Everything', 'this is a "quote"']);

    expect((string) $rule)->toBe('in:"Life, the Universe and Everything","this is a ""quote"""');

    $rule = Rule::in(collect([1, 2, 3, 4]));

    expect((string) $rule)->toBe('in:"1","2","3","4"');

    $rule = Rule::in(collect([1, 2, 3, 4]));

    expect((string) $rule)->toBe('in:"1","2","3","4"');

    $rule = new In(["a,b\nc,d"]);

    expect((string) $rule)->toBe("in:\"a,b\nc,d\"");

    $rule = Rule::in([1, 2, 3, 4]);

    expect((string) $rule)->toBe('in:"1","2","3","4"');

    $rule = Rule::in(collect([1, 2, 3, 4]));

    expect((string) $rule)->toBe('in:"1","2","3","4"');

    $rule = Rule::in(new Values);

    expect((string) $rule)->toBe('in:"1","2","3","4"');

    $rule = Rule::in('1', '2', '3', '4');

    expect((string) $rule)->toBe('in:"1","2","3","4"');

    $rule = new In('1', '2', '3', '4');

    expect((string) $rule)->toBe('in:"1","2","3","4"');

    $rule = Rule::in([StringStatus::done]);

    expect((string) $rule)->toBe('in:"done"');

    $rule = Rule::in([IntegerStatus::done]);

    expect((string) $rule)->toBe('in:"2"');

    $rule = Rule::in([PureEnum::one]);

    expect((string) $rule)->toBe('in:"one"');
});

test('in rule validation', function () {
    $trans = new Translator(new ArrayLoader, 'en');

    $v = new Validator($trans, ['x' => 'foo'], ['x' => Rule::in('foo', 'bar')]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['x' => 'foo'], ['x' => (string) Rule::in('foo', 'bar')]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['x' => 'foo'], ['x' => [Rule::in('bar', 'baz')]]);
    expect($v->passes())->toBeFalse();

    $v = new Validator($trans, ['x' => 'foo'], ['x' => ['required', Rule::in('foo', 'bar')]]);
    expect($v->passes())->toBeTrue();
});

test('in rule is not loosy bypassed', function (mixed $value, bool $expectation) {
    $trans = new Translator(new ArrayLoader, 'en');

    $v = new Validator($trans, ['x' => $value], ['x' => ['in:1,2,3']]);

    expect($v->passes())->toBe($expectation);
})->with([
    [' 1', false],
    ['1 ', false],
    ["\t1", false],
    ["1\n", false],
    ['01', false],
    ['+1', false],
    ['1.0', false],
    ['1e0', false],
    ['1', true],
]);
