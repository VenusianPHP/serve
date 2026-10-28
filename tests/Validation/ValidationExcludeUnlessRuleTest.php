<?php

namespace Venusian\Serve\Tests\Validation;

use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Rule;
use Mariner\Validation\Rules\ExcludeUnless;
use Mariner\Validation\Validator;

beforeEach(function () {
    $this->translator = new Translator(new ArrayLoader, 'en');
});

test('instance of', function () {
    expect(Rule::excludeUnless(true))->toBeInstanceOf(ExcludeUnless::class);
});

test('boolean condition true', function () {
    $rule = Rule::excludeUnless(true);
    expect((string) $rule)->toBe('');
});

test('boolean condition false', function () {
    $rule = Rule::excludeUnless(false);
    expect((string) $rule)->toBe('exclude');
});

test('closure condition true', function () {
    $rule = Rule::excludeUnless(fn () => true);
    expect((string) $rule)->toBe('');
});

test('closure condition false', function () {
    $rule = Rule::excludeUnless(fn () => false);
    expect((string) $rule)->toBe('exclude');
});

test('field is excluded when condition false', function () {
    $validator = new Validator(
        $this->translator,
        ['name' => 'Taylor', 'extra' => 'value'],
        [
            'name' => 'required|string',
            'extra' => [Rule::excludeUnless(false), 'string'],
        ],
    );

    expect($validator->passes())->toBeTrue();
    expect($validator->validated())->not->toHaveKey('extra');
});

test('field is kept when condition true', function () {
    $validator = new Validator(
        $this->translator,
        ['name' => 'Taylor', 'extra' => 'value'],
        [
            'name' => 'required|string',
            'extra' => [Rule::excludeUnless(true), 'string'],
        ],
    );

    expect($validator->passes())->toBeTrue();
    expect($validator->validated())->toHaveKey('extra');
});

test('invalid condition throws', function () {

    Rule::excludeUnless('invalid');
})->throws(\InvalidArgumentException::class);
