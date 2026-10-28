<?php

namespace Venusian\Serve\Tests\Validation;

use Exception;
use Generator;
use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Rules\ExcludeIf;
use Mariner\Validation\Validator;
use InvalidArgumentException;
use stdClass;

test('it returns string version of rule when cast', function () {
    $rule = new ExcludeIf(function () {
        return true;
    });

    expect((string) $rule)->toBe('exclude');

    $rule = new ExcludeIf(function () {
        return false;
    });

    expect((string) $rule)->toBe('');

    $rule = new ExcludeIf(true);

    expect((string) $rule)->toBe('exclude');

    $rule = new ExcludeIf(false);

    expect((string) $rule)->toBe('');
});

test('it accepts callable and boolean arguments', function () {
    new ExcludeIf(false);
    new ExcludeIf(true);
    new ExcludeIf(fn () => true);

    $this->addToAssertionCount(1);
});

test('it rejects non callable non boolean arguments', function ($condition) {
    try {
        new ExcludeIf($condition);
        $this->fail('The ExcludeIf constructor must not accept '.gettype($condition));
    } catch (InvalidArgumentException $exception) {
        expect($exception->getMessage())->toBe('The provided condition must be a callable or boolean.');
    }
})->with('dataProviderItRejectsNonCallableNonBooleanArguments');

dataset('dataProviderItRejectsNonCallableNonBooleanArguments', function () {
    yield 'int' => [1];
    yield 'float' => [1.1];
    yield 'string' => ['phpinfo'];
    yield 'object' => [new stdClass];
    yield 'null' => [null];
});

test('it throws exception if rule is not serializable', function () {

    serialize(new ExcludeIf(function () {
        return true;
    }));
})->throws(Exception::class);

test('exclude if rule validation', function () {
    $ruleTrue = new ExcludeIf(true);

    $ruleFalse = new ExcludeIf(false);

    $trans = new Translator(new ArrayLoader, 'en');

    $data = ['foo' => 'FOO', 'bar' => 'BAR'];

    $v = new Validator($trans, $data, ['foo' => $ruleTrue, 'bar' => 'nullable']);
    expect($v->passes())->toBeTrue();
    expect($v->validated())->toBe(['bar' => 'BAR']);

    $v = new Validator($trans, $data, ['foo' => (string) $ruleTrue, 'bar' => 'nullable']);
    expect($v->passes())->toBeTrue();
    expect($v->validated())->toBe(['bar' => 'BAR']);

    $v = new Validator($trans, $data, ['foo' => [$ruleTrue], 'bar' => 'nullable']);
    expect($v->passes())->toBeTrue();
    expect($v->validated())->toBe(['bar' => 'BAR']);

    $v = new Validator($trans, $data, ['foo' => $ruleFalse, 'bar' => 'nullable']);
    expect($v->passes())->toBeTrue();
    expect($v->validated())->toBe($data);
});
