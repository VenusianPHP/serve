<?php

namespace Venusian\Serve\Tests\Validation;

use Voyager\Vessel\ControlPanel;
use Mariner\Contracts\Validation\Rule as RuleContract;
use Voyager\Http\Client\Factory as HttpFactory;
use Mariner\NutsAndBolts\Facades\Facade;
use Voyager\NutsAndBolts\Str;
use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Rules\Password;
use Mariner\Validation\ValidationServiceProvider;
use Mariner\Validation\Validator;
use InvalidArgumentException;
use Voyager\Http\Client\Factory;
use Venusian\Serve\Tests\Fixtures\WebApp;

test('string', function () {
    passwordRuleFails(Password::min(3), [['foo' => 'bar'], ['foo']], [
        'validation.string',
        'validation.min.string',
    ]);

    passwordRuleFails(Password::min(3), [1234567, 545], [
        'validation.string',
    ]);

    passwordRulePasses(Password::min(3), ['abcd', '454qb^', '接2133手田']);
});

test('min', function () {
    passwordRuleFails(new Password(8), ['a', 'ff', '12'], [
        'validation.min.string',
    ]);

    passwordRuleFails(Password::min(3), ['a', 'ff', '12'], [
        'validation.min.string',
    ]);

    passwordRulePasses(Password::min(3), ['333', 'abcd']);
    passwordRulePasses(new Password(8), ['88888888']);
});

test('max', function () {
    passwordRuleFails(Password::min(2)->max(4), ['aaaaa', '11111111'], [
        'validation.max.string',
    ]);

    passwordRulePasses(Password::min(2)->max(3), ['aa', '111']);
});

test('conditional', function () {
    $is_privileged_user = true;
    $rule = (new Password(8))->when($is_privileged_user, function ($rule) {
        $rule->symbols();
    });
    expect($rule)->toBeInstanceOf(Password::class);

    passwordRuleFails($rule, ['aaaaaaaa', '11111111'], [
        'validation.password.symbols',
    ]);

    $is_privileged_user = false;
    $rule = (new Password(8))->when($is_privileged_user, function ($rule) {
        $rule->symbols();
    });
    expect($rule)->toBeInstanceOf(Password::class);

    passwordRulePasses($rule, ['aaaaaaaa', '11111111']);
});

test('mixed case', function () {
    passwordRuleFails(Password::min(2)->mixedCase(), ['nn', 'MM'], [
        'validation.password.mixed',
    ]);

    passwordRulePasses(Password::min(2)->mixedCase(), ['Nn', 'Mn', 'âA']);
});

test('letters', function () {
    passwordRuleFails(Password::min(2)->letters(), ['11', '22', '^^', '``', '**'], [
        'validation.password.letters',
    ]);

    passwordRulePasses(Password::min(2)->letters(), ['1a', 'b2', 'â1', '1 京都府']);
});

test('numbers', function () {
    passwordRuleFails(Password::min(2)->numbers(), ['aa', 'bb', '  a', '京都府'], [
        'validation.password.numbers',
    ]);

    passwordRulePasses(Password::min(2)->numbers(), ['1a', 'b2', '00', '京都府 1']);
});

test('default rules', function () {
    passwordRuleFails(Password::min(3), [null], [
        'validation.string',
        'validation.min.string',
    ]);
});

test('symbols', function () {
    passwordRuleFails(Password::min(2)->symbols(), ['ab', '1v'], [
        'validation.password.symbols',
    ]);

    passwordRulePasses(Password::min(2)->symbols(), ['n^d', 'd^!', 'âè$', '金廿土弓竹中；']);
});

test('uncompromised', function () {
    // These are treated as compromised, with a breach count high enough to still
    // be considered compromised past the raised threshold used further below.
    fakePwnedPasswordsApi([
        '123456' => 25000000,
        'password' => 10000000,
        'welcome' => 500000,
        'abc123' => 300000,
        '123456789' => 20000000,
        '12345678' => 15000000,
        'nuno' => 5000000,
    ]);

    passwordRuleFails(Password::min(2)->uncompromised(), [
        '123456',
        'password',
        'welcome',
        'abc123',
        '123456789',
        '12345678',
        'nuno',
    ], [
        'validation.password.uncompromised',
    ]);

    passwordRulePasses(Password::min(2)->uncompromised(9999999), [
        'nuno',
    ]);

    passwordRulePasses(Password::min(2)->uncompromised(), [
        '手田日尸Ｚ難金木水口火女月土廿卜竹弓一十山',
        '!p8VrB',
        '&xe6VeKWF#n4',
        '%HurHUnw7zM!',
        'rundeliekend',
        '7Z^k5EvqQ9g%c!Jt9$ufnNpQy#Kf',
        'NRs*Gz2@hSmB$vVBSPDfqbRtEzk4nF7ZAbM29VMW$BPD%b2U%3VmJAcrY5eZGVxP%z%apnwSX',
    ]);
});

function fakePwnedPasswordsApi(array $breachCountsByPassword): void
{
    $http = new HttpFactory;
    app()->registerInstance(HttpFactory::class, $http);

    $http->fake(function ($request) use ($breachCountsByPassword) {
        $prefix = Str::after($request->url(), '/range/');

        foreach ($breachCountsByPassword as $password => $count) {
            $hash = strtoupper(sha1($password));

            if (str_starts_with($hash, $prefix)) {
                return HttpFactory::response(substr($hash, 5).':'.$count);
            }
        }

        return HttpFactory::response('');
    });
}

test('messages order', function () {
    fakePwnedPasswordsApi([
        'abcabcabc!' => 1000,
    ]);

    $makeRules = function () {
        return ['required', Password::min(8)->mixedCase()->numbers()];
    };

    passwordRuleFails($makeRules(), [null], [
        'validation.required',
    ]);

    passwordRuleFails($makeRules(), ['foo', 'azdazd'], [
        'validation.min.string',
        'validation.password.mixed',
        'validation.password.numbers',
    ]);

    passwordRuleFails($makeRules(), ['1231231'], [
        'validation.min.string',
        'validation.password.mixed',
    ]);

    passwordRuleFails($makeRules(), ['4564654564564'], [
        'validation.password.mixed',
    ]);

    passwordRuleFails($makeRules(), ['aaaaaaaaa', 'TJQSJQSIUQHS'], [
        'validation.password.mixed',
        'validation.password.numbers',
    ]);

    passwordRulePasses($makeRules(), ['4564654564564Abc']);

    $makeRules = function () {
        return ['nullable', 'confirmed', Password::min(8)->letters()->symbols()->uncompromised()];
    };

    passwordRulePasses($makeRules(), [null]);

    passwordRuleFails($makeRules(), ['foo', 'azdazd'], [
        'validation.min.string',
        'validation.password.symbols',
    ]);

    passwordRuleFails($makeRules(), ['1231231'], [
        'validation.min.string',
        'validation.password.letters',
        'validation.password.symbols',
    ]);

    passwordRuleFails($makeRules(), ['aaaaaaaaa', 'TJQSJQSIUQHS'], [
        'validation.password.symbols',
    ]);

    passwordRuleFails($makeRules(), ['4564654564564'], [
        'validation.password.letters',
        'validation.password.symbols',
    ]);

    passwordRuleFails($makeRules(), ['abcabcabc!'], [
        'validation.password.uncompromised',
    ]);

    $v = new Validator(
        app('translator'),
        ['my_password' => 'Nuno'],
        ['my_password' => ['nullable', 'confirmed', Password::min(3)->letters()]]
    );

    expect($v->passes())->toBeFalse();

    expect($v->messages()->toArray())->toBe(['my_password' => ['validation.confirmed']]);
});

test('it can use default', function () {
    expect(Password::default())->toBeInstanceOf(Password::class);
});

test('it can set default using', function () {
    expect(Password::default())->toBeInstanceOf(Password::class);

    $password = Password::min(3);
    $password2 = Password::min(2)->mixedCase();

    Password::defaults(function () use ($password) {
        return $password;
    });

    passwordRulePasses(Password::default(), ['abcd', '454qb^', '接2133手田']);
    expect(Password::default())->toBe($password);
    expect(Password::required())->toBeInstanceOf(Password::class);
    expect(Password::sometimes())->toBeInstanceOf(Password::class);

    Password::defaults($password2);
    passwordRulePasses(Password::default(), ['Nn', 'Mn', 'âA']);
    expect(Password::default())->toBe($password2);
    expect(Password::required())->toBeInstanceOf(Password::class);
    expect(Password::sometimes())->toBeInstanceOf(Password::class);
});

test('it cannot set default using given string', function () {

    Password::defaults('required|password');
})->throws(InvalidArgumentException::class, 'given callback should be callable');

test('it passes with valid data if the same validation rules are reused', function () {
    $rules = [
        'password' => Password::default(),
    ];

    $v = new Validator(
        app('translator'),
        ['password' => '1234'],
        $rules
    );

    expect($v->passes())->toBeFalse();

    $v1 = new Validator(
        app('translator'),
        ['password' => '12341234'],
        $rules
    );

    expect($v1->passes())->toBeTrue();
});

test('custom messages', function () {
    $rules = [
        'my_password' => Password::min(6)->letters(),
    ];

    $messages = [
        'min' => 'Message for validating length',
        'password.letters' => 'Message for validating letters',
    ];

    $v = new Validator(
        app('translator'),
        ['my_password' => '1234'],
        $rules,
        $messages,
    );

    expect($v->passes())->toBeFalse();

    expect($v->messages()->toArray())->toBe(['my_password' => array_values($messages)]);
});

test('passes with custom rules', function () {
    $closureRule = function ($attribute, $value, $fail) {
        if ($value !== 'aa') {
            $fail('Custom rule closure failed');
        }
    };

    $ruleObject = new class implements RuleContract
    {
        public function passes($attribute, $value)
        {
            return $value === 'aa';
        }

        public function message()
        {
            return 'Custom rule object failed';
        }
    };

    passwordRulePasses(Password::min(2)->rules($closureRule), ['aa']);
    passwordRulePasses(Password::min(2)->rules([$closureRule]), ['aa']);
    passwordRulePasses(Password::min(2)->rules($ruleObject), ['aa']);
    passwordRulePasses(Password::min(2)->rules([$closureRule, $ruleObject]), ['aa']);

    passwordRuleFails(Password::min(2)->rules($closureRule), ['ab'], [
        'Custom rule closure failed',
    ]);

    passwordRuleFails(Password::min(2)->rules($ruleObject), ['ab'], [
        'Custom rule object failed',
    ]);
});

test('can retrieve all rules applied', function () {
    $password = Password::min(2)
        ->max(4)
        ->mixedCase()
        ->numbers()
        ->letters()
        ->symbols();

    expect($password->appliedRules())->toBe([
        'min' => 2,
        'max' => 4,
        'mixedCase' => true,
        'letters' => true,
        'numbers' => true,
        'symbols' => true,
        'uncompromised' => false,
        'compromisedThreshold' => 0,
        'customRules' => [],
    ]);

    $password = Password::min(2);

    expect($password->appliedRules())->toBe([
        'min' => 2,
        'max' => null,
        'mixedCase' => false,
        'letters' => false,
        'numbers' => false,
        'symbols' => false,
        'uncompromised' => false,
        'compromisedThreshold' => 0,
        'customRules' => [],
    ]);
});

test('required', function () {
    passwordRuleFails(Password::required(), [null], [
        'validation.required',
    ]);

    passwordRulePasses(Password::required(), ['12345678', 'password123']);

    passwordRuleFails([Password::required()], ['short'], [
        'validation.min.string',
    ]);

    passwordRulePasses(Password::required()->mixedCase()->numbers(), ['Password1']);

    // Ensure it still correct when using array
    passwordRulePasses([Password::required()], ['12345678', 'password123']);

    passwordRuleFails([Password::required()], ['short'], [
        'validation.min.string',
    ]);

    passwordRulePasses(['string', Password::required()], ['12345678', 'password123']);

    passwordRulePasses([Password::required()->mixedCase()->numbers()], ['Password1']);

    // Test with custom defaults
    Password::defaults(Password::min(6)->letters());

    passwordRuleFails(Password::required(), [null], [
        'validation.required',
    ]);

    passwordRulePasses(Password::required(), ['Password123', 'password123']);
    passwordRulePasses([Password::required()], ['Password123', 'password123']);
});

test('sometimes', function () {
    passwordRuleFails(Password::sometimes(), ['short'], [
        'validation.min.string',
    ]);

    passwordRulePasses(Password::sometimes(), ['12345678', 'password123']);

    passwordRuleFails([Password::sometimes()], ['12345'], [
        'validation.min.string',
    ]);

    passwordRulePasses(Password::sometimes()->mixedCase()->numbers(), ['Password1']);

    // Ensure it still correct when using array
    passwordRulePasses([Password::sometimes()], ['12345678', 'password123']);

    passwordRuleFails([Password::sometimes()], ['12345'], [
        'validation.min.string',
    ]);

    passwordRulePasses(['string', Password::sometimes()], ['12345678', 'password123']);

    passwordRulePasses([Password::sometimes()->mixedCase()->numbers()], ['Password1']);

    // Test with custom defaults
    Password::defaults(Password::min(6)->letters());

    passwordRulePasses(Password::sometimes(), ['Password123', 'password123']);
    passwordRulePasses([Password::sometimes()], ['Password123', 'password123']);
});

test('required with missing value', function () {
    $v = new Validator(
        app('translator'),
        [],
        ['password' => [Password::required()]]
    );

    expect($v->passes())->toBeFalse();
    expect($v->messages()->toArray())->toHaveKey('password');
    expect($v->messages()->first('password'))->toContain('required');

    $v = \Mariner\NutsAndBolts\Facades\Validator::make(
        [],
        [
            'password' => [Password::required()],
        ]
    );

    expect($v->passes())->toBeFalse();
});

test('nullable with empty string', function () {
    $v = new Validator(
        app('translator'),
        ['password' => ''],
        ['password' => ['nullable', Password::min(8)->letters()->numbers()]]
    );

    expect($v->passes())->toBeTrue();

    $v = new Validator(
        app('translator'),
        ['password' => null],
        ['password' => ['nullable', Password::min(8)->letters()->numbers()]]
    );

    expect($v->passes())->toBeTrue();

    $v = new Validator(
        app('translator'),
        ['password' => ''],
        ['password' => ['nullable', Password::sometimes()->min(8)->letters()->numbers()]]
    );

    expect($v->passes())->toBeTrue();
});

test('it can returns as unpacked array', function () {
    expect([...Password::required()])->toBe(['required', 'string', 'min:8']);
    expect([...Password::sometimes()])->toBe(['sometimes', 'string', 'min:8']);
});

test('to password rules string', function () {
    expect(Password::min(8)->toPasswordRulesString())->toBe('minlength: 8;');

    expect(Password::min(8)->max(64)->toPasswordRulesString())->toBe('minlength: 8; maxlength: 64;');

    expect(Password::min(8)->mixedCase()->toPasswordRulesString())->toBe('minlength: 8; required: lower; required: upper;');

    expect(Password::min(8)->letters()->toPasswordRulesString())->toBe('minlength: 8; required: lower;');

    expect(Password::min(8)->numbers()->toPasswordRulesString())->toBe('minlength: 8; required: digit;');

    expect(Password::min(8)->symbols()->toPasswordRulesString())->toBe('minlength: 8; required: special;');

    expect(Password::min(12)->max(64)->mixedCase()->numbers()->symbols()->toPasswordRulesString())->toBe('minlength: 12; maxlength: 64; required: lower; required: upper; required: digit; required: special;');
});

function passwordRulePasses($rule, $values)
{
    passwordRuleAssertValidationRules($rule, $values, true, []);
}

function passwordRuleFails($rule, $values, $messages)
{
    passwordRuleAssertValidationRules($rule, $values, false, $messages);
}

function passwordRuleAssertValidationRules($rule, $values, $result, $messages)
{
    foreach ($values as $value) {
        $v = new Validator(
            app('translator'),
            ['my_password' => $value, 'my_password_confirmation' => $value],
            ['my_password' => is_object($rule) ? clone $rule : $rule]
        );

        expect($v->passes())->toBe($result);

        expect($v->messages()->toArray())->toBe($result ? [] : ['my_password' => $messages]);
    }
}

beforeEach(function () {
    $container = WebApp::boot();

    $container->bind('translator', function () {
        return new Translator(
            new ArrayLoader, 'en'
        );
    });

    $container->registerSingleton(Factory::class);

    Facade::setFacadeApplication($container);

    (new ValidationServiceProvider($container))->register();
});

afterEach(function () {
    ControlPanel::setInstance(null);

    Facade::clearResolvedInstances();

    Facade::setFacadeApplication(null);

    Password::$defaultCallback = null;

    WebApp::tearDown($this);
});

test('a defaults callback that returns another rule falls back to the built-in default', function () {
    Password::defaults(fn () => new class implements \Mariner\Contracts\Validation\Rule
    {
        public function passes($attribute, $value)
        {
            return true;
        }

        public function message()
        {
            return '';
        }
    });

    expect(Password::default())->toBeInstanceOf(Password::class)
        ->and(Password::required())->toBeInstanceOf(Password::class);
});
