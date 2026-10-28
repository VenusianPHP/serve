<?php

namespace Venusian\Serve\Tests\Validation;

use Exception;
use Generator;
use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Rules\ProhibitedIf;
use Mariner\Validation\Validator;
use InvalidArgumentException;
use stdClass;

test('it returns string version of rule when cast', function () {
    $rule = new ProhibitedIf(function () {
        return true;
    });

    expect((string) $rule)->toBe('prohibited');

    $rule = new ProhibitedIf(function () {
        return false;
    });

    expect((string) $rule)->toBe('');

    $rule = new ProhibitedIf(true);

    expect((string) $rule)->toBe('prohibited');

    $rule = new ProhibitedIf(false);

    expect((string) $rule)->toBe('');
});

test('it accepts callable and boolean arguments', function () {
    new ProhibitedIf(false);
    new ProhibitedIf(true);
    new ProhibitedIf(fn () => true);

    $this->addToAssertionCount(1);
});

test('it rejects non callable non boolean arguments', function ($condition) {
    try {
        new ProhibitedIf($condition);
        $this->fail('The ProhibitedIf constructor must not accept '.gettype($condition));
    } catch (InvalidArgumentException $exception) {
        expect($exception->getMessage())->toBe('The provided condition must be a callable or boolean.');
    }
})->with('dataProviderItRejectsNonCallableNonBooleanArguments');

dataset('dataProviderItRejectsNonCallableNonBooleanArguments', function () {
    yield 'int' => [1];
    yield 'float' => [1.1];
    yield 'string' => ['phpinfo'];
    yield 'object' => [new stdClass];
});

test('it throws exception if rule is not serializable', function () {

    serialize(new ProhibitedIf(function () {
        return true;
    }));
})->throws(Exception::class);

test('prohibited if rule validation', function () {
    $trans = new Translator(new ArrayLoader, 'en');

    $rule = new ProhibitedIf(true);

    $v = new Validator($trans, ['y' => 'foo'], ['x' => $rule]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['y' => 'foo'], ['x' => (string) $rule]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['y' => 'foo'], ['x' => [$rule]]);
    expect($v->passes())->toBeTrue();

    $v = new Validator($trans, ['x' => 'foo'], ['x' => ['string', $rule]]);
    expect($v->fails())->toBeTrue();

    $rule = new ProhibitedIf(false);

    $v = new Validator($trans, ['x' => 'foo'], ['x' => ['string', $rule]]);
    expect($v->passes())->toBeTrue();
});
