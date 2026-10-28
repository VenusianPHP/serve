<?php

namespace Venusian\Serve\Tests\Validation;

use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Rule;
use Mariner\Validation\ValidationRuleParser;
use Mariner\Validation\Validator;

test('simple rule builders', function () {
    expect(Rule::accepted())->toBe('accepted');
    expect(Rule::activeUrl())->toBe('active_url');
    expect(Rule::base64())->toBe('base64');
    expect(Rule::bail())->toBe('bail');
    expect(Rule::boolean())->toBe('boolean');
    expect(Rule::boolean(false))->toBe('boolean');
    expect(Rule::boolean(true))->toBe('boolean:strict');
    expect(Rule::declined())->toBe('declined');
    expect(Rule::exclude())->toBe('exclude');
    expect(Rule::filled())->toBe('filled');
    expect(Rule::hexColor())->toBe('hex_color');
    expect(Rule::json())->toBe('json');
    expect(Rule::list())->toBe('list');
    expect(Rule::macAddress())->toBe('mac_address');
    expect(Rule::missing())->toBe('missing');
    expect(Rule::nullable())->toBe('nullable');
    expect(Rule::present())->toBe('present');
    expect(Rule::prohibited())->toBe('prohibited');
    expect(Rule::required())->toBe('required');
    expect(Rule::sometimes())->toBe('sometimes');
    expect(Rule::ulid())->toBe('ulid');
});

test('conditional rule builders', function () {
    expect((string) Rule::acceptedIf('user', null))->toBe('accepted_if:user,null');
    expect((string) Rule::declinedIf('user', 'foo'))->toBe('declined_if:user,foo');
    expect((string) Rule::missingIf('user', 24))->toBe('missing_if:user,24');
    expect((string) Rule::missingUnless('user', 24.5))->toBe('missing_unless:user,24.5');
    expect((string) Rule::presentIf('user', 'foo'))->toBe('present_if:user,foo');
    expect((string) Rule::presentUnless('user', null))->toBe('present_unless:user,null');
    expect((string) Rule::acceptedIf('user', 'foo', 'bar'))->toBe('accepted_if:user,foo,bar');
    expect((string) Rule::declinedIf('user', false, true))->toBe('declined_if:user,false,true');
    expect((string) Rule::missingIf('user', ['foo', 'bar']))->toBe('missing_if:user,foo,bar');
    expect((string) Rule::missingUnless('user', [true, false]))->toBe('missing_unless:user,true,false');
    expect((string) Rule::presentIf('user', 'foo', 'bar'))->toBe('present_if:user,foo,bar');
    expect((string) Rule::presentUnless('user', null, 'foo'))->toBe('present_unless:user,null,foo');
});

test('additional rule builders', function () {
    expect((string) Rule::confirmed())->toBe('confirmed');
    expect((string) Rule::confirmed()->customField('email_confirmation'))->toBe('confirmed:email_confirmation');
    expect((string) Rule::confirmed()->customField('0'))->toBe('confirmed:0');
    expect((string) Rule::currentPassword())->toBe('current_password');
    expect((string) Rule::currentPassword()->guard('api'))->toBe('current_password:api');
    expect((string) Rule::distinct())->toBe('distinct');
    expect((string) Rule::distinct()->strict())->toBe('distinct:strict');
    expect((string) Rule::distinct()->ignoreCase())->toBe('distinct:ignore_case');
    expect((string) Rule::distinct()->strict()->ignoreCase())->toBe('distinct:strict,ignore_case');
    expect((string) Rule::distinct()->ignoreCase()->strict())->toBe('distinct:ignore_case,strict');
    expect((string) Rule::distinct()->strict()->ignoreCase()->strict()->ignoreCase())->toBe('distinct:strict,ignore_case');
    expect((string) Rule::excludeWith('foo'))->toBe('exclude_with:foo');
    expect((string) Rule::excludeWithout('foo'))->toBe('exclude_without:foo');
    expect((string) Rule::excludeWithout('foo', 'bar'))->toBe('exclude_without:foo,bar');
    expect((string) Rule::inArray('foo'))->toBe('in_array:foo');
    expect((string) Rule::inArrayKeys(['foo', 'bar']))->toBe('in_array_keys:foo,bar');
    expect((string) Rule::inArrayKeys('foo', 'bar'))->toBe('in_array_keys:foo,bar');
    expect((string) Rule::ip())->toBe('ip');
    expect((string) Rule::ip()->version(4))->toBe('ipv4');
    expect((string) Rule::ip()->version(6))->toBe('ipv6');
    expect((string) Rule::missingWith(['foo', 'bar']))->toBe('missing_with:foo,bar');
    expect((string) Rule::missingWith('foo', 'bar'))->toBe('missing_with:foo,bar');
    expect((string) Rule::missingWithAll(['foo', 'bar']))->toBe('missing_with_all:foo,bar');
    expect((string) Rule::presentWith(['foo', 'bar']))->toBe('present_with:foo,bar');
    expect((string) Rule::presentWith('foo', 'bar'))->toBe('present_with:foo,bar');
    expect((string) Rule::presentWithAll(['foo', 'bar']))->toBe('present_with_all:foo,bar');
    expect((string) Rule::prohibitedIfAccepted('foo'))->toBe('prohibited_if_accepted:foo');
    expect((string) Rule::prohibitedIfDeclined('foo'))->toBe('prohibited_if_declined:foo');
    expect((string) Rule::prohibits(['foo', 'bar']))->toBe('prohibits:foo,bar');
    expect((string) Rule::prohibits('foo', 'bar'))->toBe('prohibits:foo,bar');
    expect((string) Rule::regex('/^foo$/'))->toBe('regex:/^foo$/');
    expect((string) Rule::notRegex('/^foo$/'))->toBe('not_regex:/^foo$/');
    expect((string) Rule::requiredArrayKeys(['foo', 'bar']))->toBe('required_array_keys:foo,bar');
    expect((string) Rule::requiredArrayKeys('foo', 'bar'))->toBe('required_array_keys:foo,bar');
    expect((string) Rule::requiredIfAccepted('foo'))->toBe('required_if_accepted:foo');
    expect((string) Rule::requiredIfDeclined('foo'))->toBe('required_if_declined:foo');
    expect((string) Rule::requiredWith(['foo', 'bar']))->toBe('required_with:foo,bar');
    expect((string) Rule::requiredWith('foo', 'bar'))->toBe('required_with:foo,bar');
    expect((string) Rule::requiredWithAll(['foo', 'bar']))->toBe('required_with_all:foo,bar');
    expect((string) Rule::requiredWithout(['foo', 'bar']))->toBe('required_without:foo,bar');
    expect((string) Rule::requiredWithoutAll(['foo', 'bar']))->toBe('required_without_all:foo,bar');
    expect((string) Rule::requiredWithoutAll('foo', 'bar'))->toBe('required_without_all:foo,bar');
    expect((string) Rule::timezone(['per_country', 'US']))->toBe('timezone:per_country,US');
    expect((string) Rule::url()->protocols(['http', 'https']))->toBe('url:http,https');
    expect((string) Rule::uuid()->version(4))->toBe('uuid:4');
    expect((string) Rule::uuid()->version(0))->toBe('uuid:0');
    expect((string) Rule::uuid()->version('nil'))->toBe('uuid:nil');
    expect((string) Rule::uuid()->version('max'))->toBe('uuid:max');
});

test('conditional rule parameters are preserved', function ($method, $value) {
    $rule = Rule::$method('other\\",field', $value);

    expect(ValidationRuleParser::parse($rule)[1])->toBe(['other\\",field', $value]);
})->with('conditionalRuleParametersProvider');

dataset('conditionalRuleParametersProvider', function () {
    foreach (['acceptedIf', 'declinedIf', 'missingIf', 'missingUnless', 'presentIf', 'presentUnless'] as $method) {
        foreach (['foo,bar', '"quoted"', 'foo"bar,baz', 'foo|bar', 'foo,bar\\', 'foo\\",bar', '\\",admin,true', ''] as $value) {
            yield "$method: $value" => [$method, $value];
        }
    }
});

test('conditional rules validate escaped values', function () {
    $translator = new Translator(new ArrayLoader, 'en');

    foreach ([
        ['acceptedIf', ['other' => 'foo,bar', 'field' => false]],
        ['declinedIf', ['other' => 'foo,bar', 'field' => true]],
        ['missingIf', ['other' => 'foo,bar', 'field' => 'value']],
        ['missingUnless', ['other' => 'foo', 'field' => 'value']],
        ['presentIf', ['other' => 'foo,bar']],
        ['presentUnless', ['other' => 'foo']],
    ] as [$method, $data]) {
        $validator = new Validator($translator, $data, ['field' => [Rule::$method('other', 'foo,bar')]]);

        expect($validator->passes())->toBeFalse();
    }
});

test('conditional rule parameters cannot bypass validation', function ($method, $value) {
    $translator = new Translator(new ArrayLoader, 'en');
    $rule = Rule::$method('other', $value);
    $data = ['other' => $value];

    if (! str_starts_with($method, 'present')) {
        $data['field'] = match ($method) {
            'acceptedIf' => false,
            'declinedIf' => true,
            default => 'value',
        };
    }

    foreach ([$rule, [$rule], [Rule::when(true, [$rule])]] as $rules) {
        expect((new Validator($translator, $data, ['field' => $rules]))->passes())->toBe(str_ends_with($method, 'Unless'));

        $differentData = array_replace($data, ['other' => 'different:'.$value]);

        expect((new Validator($translator, $differentData, ['field' => $rules]))->passes())->toBe(! str_ends_with($method, 'Unless'));
    }
})->with('conditionalRuleParametersProvider');

test('conditional rule parameters are preserved for wildcard and nested rules', function () {
    $translator = new Translator(new ArrayLoader, 'en');
    $value = 'foo\\",bar';
    $data = ['items' => [['other' => $value, 'terms' => false]]];
    $rule = Rule::acceptedIf('items.*.other', $value);

    foreach ([$rule, [$rule], Rule::forEach(fn () => [$rule])] as $rules) {
        $validator = new Validator($translator, $data, ['items.*.terms' => $rules]);

        expect($validator->passes())->toBeFalse();
        expect($validator->errors()->toArray())->toBe(['items.0.terms' => ['validation.accepted_if']]);
    }
});

test('field and key parameters are escaped', function () {
    foreach ([
        'excludeWithout', 'inArrayKeys', 'missingWith', 'missingWithAll',
        'presentWith', 'presentWithAll', 'prohibits', 'requiredArrayKeys',
        'requiredWith', 'requiredWithAll', 'requiredWithout', 'requiredWithoutAll',
    ] as $method) {
        $parameters = ['foo,bar', '"quoted"', 'foo,bar\\', 'foo\\",bar'];

        expect(ValidationRuleParser::parse(Rule::$method($parameters))[1])->toBe($parameters);
        expect(ValidationRuleParser::parse(Rule::$method(...$parameters))[1])->toBe($parameters);
    }

    foreach ([
        'excludeWith', 'inArray', 'prohibitedIfAccepted',
        'prohibitedIfDeclined', 'requiredIfAccepted', 'requiredIfDeclined',
    ] as $method) {
        expect(ValidationRuleParser::parse(Rule::$method('foo\\",bar'))[1])->toBe(['foo\\",bar']);
    }

    expect(ValidationRuleParser::parse(Rule::confirmed()->customField('foo\\",bar'))[1])->toBe(['foo\\",bar']);
    expect(ValidationRuleParser::parse(Rule::currentPassword()->guard('foo\\",bar'))[1])->toBe(['foo\\",bar']);
});

test('array key rules validate escaped keys', function () {
    $translator = new Translator(new ArrayLoader, 'en');

    foreach (['requiredArrayKeys', 'inArrayKeys'] as $method) {
        foreach (['foo,bar', 'foo,bar\\', 'foo\\",bar'] as $key) {
            $rules = ['settings' => [Rule::$method([$key])]];

            expect((new Validator($translator, ['settings' => [$key => 1]], $rules))->passes())->toBeTrue();
            expect((new Validator($translator, ['settings' => ['foo' => 1, 'bar' => 1]], $rules))->passes())->toBeFalse();
        }
    }
});

test('associative rule parameters are normalized', function () {
    expect(ValidationRuleParser::parse(Rule::acceptedIf('other', ['value' => 'foo,bar']))[1])->toBe(['other', 'foo,bar']);
    expect(ValidationRuleParser::parse(Rule::requiredWith(['field' => 'foo,bar']))[1])->toBe(['foo,bar']);
    expect(ValidationRuleParser::parse(Rule::requiredArrayKeys(['key' => 'foo,bar']))[1])->toBe(['foo,bar']);
});

test('conditional rules preserve scalar semantics', function () {
    $translator = new Translator(new ArrayLoader, 'en');

    foreach (['acceptedIf', 'declinedIf', 'missingIf', 'missingUnless', 'presentIf', 'presentUnless'] as $method) {
        expect(ValidationRuleParser::parse(Rule::$method('other', [true, false, null, 1, 1.5]))[1])->toBe(['other', 'true', 'false', 'null', '1', '1.5']);
    }

    foreach ([true, false, null, 1, 1.5] as $value) {
        expect((new Validator($translator, ['other' => $value, 'terms' => false], [
            'terms' => [Rule::acceptedIf('other', $value)],
        ]))->passes())->toBeFalse();
    }
});

test('distinct rules validate combined options', function () {
    $translator = new Translator(new ArrayLoader, 'en');

    foreach ([Rule::distinct()->strict()->ignoreCase(), Rule::distinct()->ignoreCase()->strict()] as $rule) {
        $rules = ['items.*' => [$rule]];

        expect((new Validator($translator, ['items' => [1, true]], $rules))->passes())->toBeTrue();
        expect((new Validator($translator, ['items' => ['foo', 'FOO']], $rules))->passes())->toBeFalse();
        expect((new Validator($translator, ['items' => [1, 1]], $rules))->passes())->toBeFalse();
    }
});

test('confirmed rule validates zero field', function () {
    $translator = new Translator(new ArrayLoader, 'en');
    $rules = ['password' => [Rule::confirmed()->customField('0')]];

    expect((new Validator($translator, ['password' => 'secret', '0' => 'secret'], $rules))->passes())->toBeTrue();
    expect((new Validator($translator, ['password' => 'secret', '0' => 'different'], $rules))->passes())->toBeFalse();
});

test('boolean rule validates strictly', function () {
    $translator = new Translator(new ArrayLoader, 'en');

    foreach ([true, false, 0, 1, '0', '1'] as $value) {
        $data = ['field' => $value];

        expect((new Validator($translator, $data, ['field' => [Rule::boolean()]]))->passes())->toBeTrue();
        expect((new Validator($translator, $data, ['field' => [Rule::boolean(true)]]))->passes())->toBe(is_bool($value));
    }
});
