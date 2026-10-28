<?php

namespace Venusian\Serve\Tests\Validation;

use Voyager\NutsAndBolts\DataObjects\Carbon;
use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Rule;
use Mariner\Validation\Rules\Date;
use Mariner\Validation\Validator;

test('default date rule', function () {
    $rule = Rule::date();
    expect((string) $rule)->toBe('date');

    $rule = new Date;
    expect((string) $rule)->toBe('date');
});

test('date format rule', function () {
    $rule = Rule::date()->format('d/m/Y');
    expect((string) $rule)->toBe('date_format:d/m/Y');
});

test('date equals rule', function () {
    $rule = Rule::date()->equals('2024-01-01');
    expect((string) $rule)->toBe('date|date_equals:2024-01-01');

    $rule = Rule::date()->format('d/m/Y')->equals(Carbon::parse('2024-01-01'));
    expect((string) $rule)->toBe('date_format:d/m/Y|date_equals:01/01/2024');
});

test('after today rule', function () {
    $rule = Rule::date()->afterToday();
    expect((string) $rule)->toBe('date|after:today');

    $rule = Rule::date()->todayOrAfter();
    expect((string) $rule)->toBe('date|after_or_equal:today');
});

test('before today rule', function () {
    $rule = Rule::date()->beforeToday();
    expect((string) $rule)->toBe('date|before:today');

    $rule = Rule::date()->todayOrBefore();
    expect((string) $rule)->toBe('date|before_or_equal:today');
});

test('past rule', function () {
    $rule = Rule::date()->past();
    expect((string) $rule)->toBe('date|before:now');

    $rule = Rule::date()->nowOrPast();
    expect((string) $rule)->toBe('date|before_or_equal:now');
});

test('future rule', function () {
    $rule = Rule::date()->future();
    expect((string) $rule)->toBe('date|after:now');

    $rule = Rule::date()->nowOrFuture();
    expect((string) $rule)->toBe('date|after_or_equal:now');
});

test('after specific date rule', function () {
    $rule = Rule::date()->after(Carbon::parse('2024-01-01'));
    expect((string) $rule)->toBe('date|after:2024-01-01');

    $rule = Rule::date()->format('d/m/Y')->after(Carbon::parse('2024-01-01'));
    expect((string) $rule)->toBe('date_format:d/m/Y|after:01/01/2024');
});

test('before specific date rule', function () {
    $rule = Rule::date()->before(Carbon::parse('2024-01-01'));
    expect((string) $rule)->toBe('date|before:2024-01-01');

    $rule = Rule::date()->format('d/m/Y')->before(Carbon::parse('2024-01-01'));
    expect((string) $rule)->toBe('date_format:d/m/Y|before:01/01/2024');
});

test('after or equal specific date rule', function () {
    $rule = Rule::date()->afterOrEqual(Carbon::parse('2024-01-01'));
    expect((string) $rule)->toBe('date|after_or_equal:2024-01-01');

    $rule = Rule::date()->format('d/m/Y')->afterOrEqual(Carbon::parse('2024-01-01'));
    expect((string) $rule)->toBe('date_format:d/m/Y|after_or_equal:01/01/2024');
});

test('before or equal specific date rule', function () {
    $rule = Rule::date()->beforeOrEqual(Carbon::parse('2024-01-01'));
    expect((string) $rule)->toBe('date|before_or_equal:2024-01-01');

    $rule = Rule::date()->format('d/m/Y')->beforeOrEqual(Carbon::parse('2024-01-01'));
    expect((string) $rule)->toBe('date_format:d/m/Y|before_or_equal:01/01/2024');
});

test('between dates rule', function () {
    $rule = Rule::date()->between(Carbon::parse('2024-01-01'), Carbon::parse('2024-02-01'));
    expect((string) $rule)->toBe('date|after:2024-01-01|before:2024-02-01');

    $rule = Rule::date()->format('d/m/Y')->between(Carbon::parse('2024-01-01'), Carbon::parse('2024-02-01'));
    expect((string) $rule)->toBe('date_format:d/m/Y|after:01/01/2024|before:01/02/2024');
});

test('between or equal dates rule', function () {
    $rule = Rule::date()->betweenOrEqual('2024-01-01', '2024-02-01');
    expect((string) $rule)->toBe('date|after_or_equal:2024-01-01|before_or_equal:2024-02-01');
});

test('chained rules', function () {
    $rule = Rule::date('Y-m-d H:i:s')
        ->format('Y-m-d')
        ->after('2024-01-01 00:00:00')
        ->before('2025-01-01 00:00:00');
    expect((string) $rule)->toBe('date_format:Y-m-d|after:2024-01-01 00:00:00|before:2025-01-01 00:00:00');

    $rule = Rule::date()
        ->format('Y-m-d')
        ->when(true, function ($rule) {
            $rule->after('2024-01-01');
        })
        ->unless(true, function ($rule) {
            $rule->before('2025-01-01');
        });
    expect((string) $rule)->toBe('date_format:Y-m-d|after:2024-01-01');
});

test('date validation', function () {
    $trans = new Translator(new ArrayLoader, 'en');

    $rule = Rule::date();

    $validator = new Validator(
        $trans,
        ['date' => 'not a date'],
        ['date' => $rule]
    );

    expect($validator->errors()->first('date'))->toBe($trans->get('validation.date'));

    $validator = new Validator(
        $trans,
        ['date' => '2024-01-01'],
        ['date' => $rule]
    );

    expect($validator->errors()->first('date'))->toBeEmpty();

    $rule = Rule::date()->between('2024-01-01', '2025-01-01');

    $validator = new Validator(
        $trans,
        ['date' => '2024-02-01'],
        ['date' => (string) $rule]
    );

    expect($validator->errors()->first('date'))->toBeEmpty();

    $rule = Rule::date()->between('2024/01/01', '2024/02/01')->format('Y/m/d');

    $validator = new Validator(
        $trans,
        ['date' => '2024/01/15'],
        ['date' => [$rule]]
    );

    expect($validator->errors()->first('date'))->toBeEmpty();
});
