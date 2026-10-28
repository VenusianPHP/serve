<?php

namespace Venusian\Serve\Tests\Validation;

use Voyager\Vessel\ControlPanel;
use Voyager\NutsAndBolts\DataObjects\Arr;
use Mariner\NutsAndBolts\Facades\Facade;
use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Rule;
use Mariner\Validation\Rules\Email;
use Mariner\Validation\ValidationServiceProvider;
use Mariner\Validation\Validator;
use Venusian\Serve\Tests\Fixtures\WebApp;

const EMAIL_RULE_ATTRIBUTE = 'my_email';
const EMAIL_RULE_ATTRIBUTE_REPLACED = 'my email';

test('basic', function () {
    fails(
        Email::default(),
        'foo',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email(),
        'foo',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Email::default(),
        12345,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email(),
        12345,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    passes(
        Email::default(),
        'taylor@laravel.com'
    );

    passes(
        Rule::email(),
        'taylor@laravel.com'
    );

    passes(
        Rule::email(),
        ['taylor@laravel.com'],
    );

    passes(
        Email::default(),
        ['taylor@laravel.com'],
    );

    passes(Email::default(), null);

    passes(Rule::email(), null);
});

function fails($rule, $values, $expectedMessages, $customValidationMessage = null)
{
    assertValidationRules($rule, $values, false, $expectedMessages, $customValidationMessage);
}

function assertValidationRules($rule, $values, $expectToPass, $expectedMessages = [], $customValidationMessage = null)
{
    $values = Arr::wrap($values);

    $translator = app('translator');

    foreach ($values as $value) {
        $v = new Validator(
            $translator,
            [EMAIL_RULE_ATTRIBUTE => $value],
            [EMAIL_RULE_ATTRIBUTE => is_object($rule) ? clone $rule : $rule],
            $customValidationMessage ? [EMAIL_RULE_ATTRIBUTE.'.email' => $customValidationMessage] : []
        );

        expect($v->passes())->toBe($expectToPass);

        expect($v->messages()->toArray())->toBe($expectToPass ? [] : [EMAIL_RULE_ATTRIBUTE => $expectedMessages]);
    }
}

function passes($rule, $values)
{
    assertValidationRules($rule, $values, true);
}

test('rfc compliant strict', function () {
    $emailThatFailsBothNonStrictButFailsInStrict = 'username@sub..example.com';
    $emailThatPassesNonStrictButFailsInStrict = '"has space"@example.com';
    $emailThatPassesBothNonStrictAndInStrict = 'plainaddress@example.com';

    fails(
        (new Email())->rfcCompliant(strict: true),
        $emailThatPassesNonStrictButFailsInStrict,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->rfcCompliant(strict: true),
        $emailThatPassesNonStrictButFailsInStrict,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        (new Email())->rfcCompliant(strict: true),
        $emailThatFailsBothNonStrictButFailsInStrict,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->rfcCompliant(strict: true),
        $emailThatFailsBothNonStrictButFailsInStrict,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    passes(
        (new Email())->rfcCompliant(strict: true),
        $emailThatPassesBothNonStrictAndInStrict
    );

    passes(
        Rule::email()->rfcCompliant(strict: true),
        $emailThatPassesBothNonStrictAndInStrict
    );
});

test('strict', function () {
    $emailThatFailsInStrict = 'username@sub..example.com';
    $emailThatPassesNonStrictButFailsInStrict = '"has space"@example.com';
    $emailThatPassesInStrict = 'plainaddress@example.com';

    fails(
        (new Email())->strict(),
        $emailThatPassesNonStrictButFailsInStrict,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->strict(),
        $emailThatFailsInStrict,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    passes(
        (new Email())->strict(),
        $emailThatPassesInStrict
    );

    passes(
        Rule::email()->strict(),
        $emailThatPassesInStrict
    );
});

test('validate mx record', function () {
    fails(
        (new Email())->validateMxRecord(),
        'plainaddress@example.com',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->validateMxRecord(),
        'plainaddress@example.com',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    passes(
        (new Email())->validateMxRecord(),
        'taylor@laravel.com'
    );

    passes(
        Rule::email()->validateMxRecord(),
        'taylor@laravel.com'
    );
});

test('prevent spoofing', function () {
    fails(
        (new Email())->preventSpoofing(),
        'admin@examрle.com',// Contains a Cyrillic 'р' (U+0440), not a Latin 'p'
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->preventSpoofing(),
        'admin@examрle.com',// Contains a Cyrillic 'р' (U+0440), not a Latin 'p'
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    $spoofingEmail = 'admin@exam'."\u{0440}".'le.com';
    fails(
        (new Email())->preventSpoofing(),
        $spoofingEmail,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->preventSpoofing(),
        $spoofingEmail,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    passes(
        (new Email())->preventSpoofing(),
        'admin@example.com'
    );

    passes(
        Rule::email()->preventSpoofing(),
        'admin@example.com'
    );

    passes(
        (new Email())->preventSpoofing(),
        'test👨‍💻@domain.com'
    );

    passes(
        Rule::email()->preventSpoofing(),
        'test👨‍💻@domain.com'
    );
});

test('with native validation', function () {
    fails(
        (new Email())->withNativeValidation(),
        'tést@domain.com',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->withNativeValidation(),
        'tést@domain.com',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    passes(
        (new Email())->withNativeValidation(),
        'admin@example.com'
    );

    passes(
        Rule::email()->withNativeValidation(),
        'admin@example.com'
    );
});

test('with native validation allow unicode', function () {
    fails(
        (new Email())->withNativeValidation(allowUnicode: true),
        'invalid.@example.com',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->withNativeValidation(allowUnicode: true),
        'invalid.@example.com',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    passes(
        (new Email())->withNativeValidation(allowUnicode: true),
        'tést@domain.com'
    );

    passes(
        Rule::email()->withNativeValidation(allowUnicode: true),
        'tést@domain.com'
    );

    passes(
        (new Email())->withNativeValidation(allowUnicode: true),
        'admin@example.com'
    );

    passes(
        Rule::email()->withNativeValidation(allowUnicode: true),
        'admin@example.com'
    );
});

test('rfc compliant non strict', function () {
    fails(
        (new Email())->rfcCompliant(),
        'invalid.@example.com',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->rfcCompliant(),
        'invalid.@example.com',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        (new Email())->rfcCompliant(),
        'test👨‍💻@domain.com',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->rfcCompliant(),
        'test👨‍💻@domain.com',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    passes(
        (new Email())->rfcCompliant(),
        'admin@example.com'
    );

    passes(
        Rule::email()->rfcCompliant(),
        'admin@example.com'
    );

    passes(
        (new Email())->rfcCompliant(),
        'tést@domain.com'
    );

    passes(
        Rule::email()->rfcCompliant(),
        'tést@domain.com'
    );
});

test('emails that pass on rfc compliant but fail on strict', function ($email) {
    passes(
        Rule::email()->rfcCompliant(),
        $email
    );

    fails(
        Rule::email()->rfcCompliant(strict: true),
        $email,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );
})->with([
    ['"has space"@example.com'],  // Quoted local part with space
    ['some(comment)@example.com'],  // Comment in local part
    ['abc."test"@example.com'],  // Mixed quoted/unquoted local part
    ['"escaped\\\"quote"@example.com'],  // Escaped quote inside quoted local part
    ['test@example'],  // Domain without TLD
    ['test@localhost'],  // Domain without TLD
    ['name@[127.0.0.1]'],  // Local-part with domain-literal IPv4 address
    ['user@[IPv6:::1]'],  // Domain-literal with unusual IPv6 short form
    ['a@[IPv6:2001:db8::1]'],  // Domain-literal with normal IPv6
    ['user@[IPv6:::]'],  // invalid shorthand IPv6
    ['"ab\\(c"@example.com'],
]);

test('emails that pass on both rfc compliant and strict', function ($email) {
    passes(
        Rule::email()->rfcCompliant(),
        $email
    );

    passes(
        Rule::email()->rfcCompliant(strict: true),
        $email
    );
})->with([
    ['plainaddress@example.com'],
    ['joe.smith@example.io'],
    ['custom-tag+dev@example.org'],
    ['hyphens--@example.org'],
    ['underscore_name@example.co.uk'],
    ['underscores__@example.org'],
    ['user@subdomain.example.com'],
    ['numbers123@domain.com'],
    ['john-doe@some-domain.com'],
    ['UPPERlower@example.org'],
    ['dots.ok@sub.domain.io'],
    ['some_email+tag@domain.dev'],
    ['a@b.c'],
    ['user@xn--bcher-kva.example'],
    ['user@bücher.example'],
]);

test('emails that fail on both rfc compliant and strict', function ($email) {
    fails(
        Rule::email()->rfcCompliant(),
        $email,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->rfcCompliant(strict: true),
        $email,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );
})->with([
    ['invalid.@example.com'],
    ['invalid@.example.com'],
    ['.invalid@example.com'],
    ['invalid@example.com.'],
    ['some..dots@example.com'],
    ['username@sub..example.com'],
    ['test@example..com'],
    ['test@@example.com'],
    ['test👨‍💻@domain.com'],
    ['username@domain-with-hyphen-.com'],
    ['()<>[]:,;@example.com'],
    ['@example.com'],
    ['[test]@example.com'],
    ['user@example.com:3000'],
    ['"unescaped"quote@example.com'],
    ['https://example.com'],
    ['with\\escape@example.com'],
]);

test('emails that pass on both rfc compliant and rfc compliant strict', function ($email) {
    passes(
        Rule::email()->rfcCompliant(),
        $email
    );

    passes(
        Rule::email()->rfcCompliant(strict: true),
        $email
    );
})->with([
    ['plainaddress@example.com'],  // Simple valid address
    ['joe.smith@example.io'],  // Dotted local part with TLD
    ['custom-tag+dev@example.org'],  // Plus tag in local part
    ['hyphens--@example.org'],  // Hyphens in local part
    ['underscore_name@example.co.uk'],  // Underscore in local part
    ['underscores__@example.org'],  // Double underscores in local part
    ['user@subdomain.example.com'],  // Subdomain in domain part
    ['numbers123@domain.com'],  // Numbers in local part
    ['john-doe@some-domain.com'],  // Hyphenated domain
    ['UPPERlower@example.org'],  // Mixed case local part
    ['dots.ok@sub.domain.io'],  // Dots in local and subdomain
    ['some_email+tag@domain.dev'],  // Email with plus tag and underscore
    ['a@b.c'],  // Minimal email
    ['user@xn--bcher-kva.example'],  // Punycode domain (bücher)
    ['user@bücher.example'],  // Unicode domain
]);

test('emails that fail with native validation ascii pass unicode', function ($email) {
    fails(
        Rule::email()->withNativeValidation(),
        $email,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    passes(
        Rule::email()->withNativeValidation(allowUnicode: true),
        $email
    );
})->with([
    ['déjà@example.com'],
    ['测试@example.com'],
]);

test('emails that fail on both with native validation ascii and unicode', function ($email) {
    fails(
        Rule::email()->withNativeValidation(),
        $email,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->withNativeValidation(allowUnicode: true),
        $email,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );
})->with([
    ['test@üñîçødé.com'],  // Unicode domain
    ['user@domain..com'],  // Double dots in domain
    ['test@.example.com'],  // Domain starts with a dot
    ['username@domain-with-hyphen-.com'],
    ['пример@пример.рф'],  // Cyrillic domain
    ['例子@例子.公司'],  // Chinese domain
    ['name@123.123.123.123'],  // Numeric domain
]);

test('emails that pass both with native validation ascii and unicode', function ($email) {
    passes(
        Rule::email()->withNativeValidation(),
        $email
    );

    passes(
        Rule::email()->withNativeValidation(allowUnicode: true),
        $email
    );
})->with([
    ['user@example.com'],
    ['user.name+tag@example.co.uk'],
    ['joe_smith@example.org'],
    ['user@[IPv6:2001:db8:1ff::a0b:dbd0]'],
    ['test@xn--bcher-kva.com'],  // Punycode for bücher.com
]);

test('emails that fail with native validation ascii pass rfc compliant', function ($email) {
    fails(
        Rule::email()->withNativeValidation(),
        $email,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    passes(
        Rule::email()->rfcCompliant(),
        $email
    );
})->with([
    ['some(comment)@example.com'],  // Comment in local part
    ['tést@example.com'],  // Accented local part
    ['user@üñîçødé.com'],  // Unicode domain
    ['user@bücher.example'],  // Unicode domain
    ['"has space"@example.com'],  // Quoted local part with space
    ['"escaped\\\"quote"@example.com'],  // Escaped quote inside quoted local part
    ['test@localhost'],  // Domain without TLD
    ['test@example'],  // Domain without TLD
    ['пример@пример.рф'],  // Cyrillic local and domain
    ['例子@例子.公司'],  // Chinese local and domain
    ['name@123.123.123.123'],  // Numeric domain
]);

test('emails that pass with native validation and rfc compliant', function ($email) {
    passes(
        Rule::email()->withNativeValidation(),
        $email
    );

    passes(
        Rule::email()->rfcCompliant(),
        $email
    );
})->with([
    ['plainaddress@example.com'],  // Simple valid address
    ['joe.smith@example.io'],  // Dot in local part
    ['custom-tag+dev@example.org'],  // Plus tag in local part
    ['hyphens--@example.org'],  // Double hyphen in local part
    ['underscore_name@example.co.uk'],  // Underscore in local part
    ['underscores__@example.org'],  // Double underscores in local part
    ['user@subdomain.example.com'],  // Subdomain in domain
    ['numbers123@domain.com'],  // Numbers in local part
    ['john-doe@some-domain.com'],  // Hyphen in domain
    ['UPPERlower@example.org'],  // Mixed-case local part
    ['dots.ok@sub.domain.io'],  // Subdomain with dot in local part
    ['some_email+tag@domain.dev'],  // Underscore and tag in local part
    ['a@b.c'],  // Minimal valid address
    ['user@xn--bcher-kva.example'],  // Punycode domain (bücher.example)
    ['user_name+tag@example.io'],  // Underscore with tag
    ['UPPERCASE@EXAMPLE.IO'],  // All uppercase local and domain
    ['abc."test"@example.com'],  // Mixed quoted/unquoted local part
    ['name@[127.0.0.1]'],  // IPv4 domain literal
    ['user@[IPv6:::1]'],  // IPv6 domain with unusual short form
    ['a@[IPv6:2001:db8::1]'],  // IPv6 domain normal form
    ['user@[IPv6:2001:db8:1ff::a0b:dbd0]'],  // Fully expanded IPv6
]);

test('emails that fail with native validation and rfc compliant', function ($email) {
    fails(
        Rule::email()->withNativeValidation(),
        $email,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->rfcCompliant(),
        $email,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );
})->with([
    ['test@@example.com'],  // Multiple @ symbols
    ['user@domain..com'],  // Double dots in domain
    ['.leadingdot@example.com'],  // Leading dot in local part
    ['with\\escape@example.com'],  // Backslash in local part
    ['@example.com'],  // Missing local part
    ['some)@example.com'],  // Unmatched parenthesis in local part
    [' space@domain.com'],  // Leading space in local part
    ['user@domain:port.com'],  // Colon in domain (mimics a port)
    ['username@domain-with-hyphen-.com'],  // Trailing hyphen in domain
]);

test('native validation vs rfc compliant', function () {
    $emailsThatPassNativeFailRfc = [
        // none I could find
    ];

    expect($emailsThatPassNativeFailRfc)->toBeEmpty();

    foreach ($emailsThatPassNativeFailRfc as $email) {
        passes(
            Rule::email()->withNativeValidation(),
            $email
        );

        fails(
            Rule::email()->rfcCompliant(),
            $email,
            ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
        );
    }
});

test('emails that pass native validation fail rfc compliant strict', function ($email) {
    passes(
        Rule::email()->withNativeValidation(),
        $email
    );

    fails(
        Rule::email()->rfcCompliant(true),
        $email,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );
})->with([
    ['abc."test"@example.com'],  // Mixed quotes in local part
    ['name@[127.0.0.1]'],  // Local-part with domain-literal IPv4 address
    ['user@[IPv6:2001:db8::1]'],  // Domain-literal with normal IPv6
    ['user@[IPv6:2001:db8:1ff::a0b:dbd0]'],  // Domain-literal with full IPv6 address
    ['"ab\\(c"@example.com'],  // Quoted local part with escaped character
]);

test('emails that fail native validation pass rfc compliant strict', function ($email) {
    fails(
        Rule::email()->withNativeValidation(),
        $email,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    passes(
        Rule::email()->rfcCompliant(true),
        $email
    );
})->with([
    ['пример@пример.рф'],  // Unicode domain in Cyrillic script
    ['例子@例子.公司'],  // Unicode domain in Chinese script
    ['name@123.123.123.123'],  // IP address in domain part
]);

test('emails that pass both native validation and rfc compliant strict', function ($email) {
    passes(
        Rule::email()->withNativeValidation(),
        $email
    );

    passes(
        Rule::email()->rfcCompliant(true),
        $email
    );
})->with([
    ['user@example.com'],  // Simple, valid email
    ['joe.smith+dev@example.co.uk'],  // Plus-tagged email with subdomain TLD
    ['user!#$%&\'*+/=?^_`{|}~@example.com'],  // Unusual valid characters in local part
]);

test('emails that fail both native validation and rfc compliant strict', function ($email) {
    fails(
        Rule::email()->withNativeValidation(),
        $email,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->rfcCompliant(true),
        $email,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );
})->with([
    ['test@@example.com'],  // Multiple @
    ['.leadingdot@example.com'],  // Leading dot in local part
    ['user@domain..com'],  // Double dots in domain
    ['test@'],  // Missing domain
    ['abc"quote@example.com'],  // Unescaped quote in local part
    ['some(comment)@example.com'],  // Local part comment
    ['"has space"@example.com'],  // Quoted local part with space
    ['user@domain(comment)'],  // Comment in domain
    ['user@[127.0.0.1(comment)]'],  // Comment in domain-literal IPv4 address
    ['some((double))comment@example.com'],  // Nested comment in local part
    ['"test\\\"quote"@example.com'],  // Escaped quote in quoted local part
    ['" leading.space"@example.com'],  // Leading space in quoted local part
]);

test('combining rules', function () {
    passes(
        (new Email())->rfcCompliant(strict: true)->preventSpoofing(),
        'test@example.com'
    );

    passes(
        Rule::email()->rfcCompliant(strict: true)->preventSpoofing(),
        'test@example.com'
    );

    fails(
        (new Email())->rfcCompliant(strict: true)->preventSpoofing()->validateMxRecord(),
        'test@example.com',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->rfcCompliant(strict: true)->preventSpoofing()->validateMxRecord(),
        'test@example.com',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    passes(
        (new Email())->preventSpoofing(),
        'test👨‍💻@domain.com'
    );

    passes(
        Rule::email()->preventSpoofing(),
        'test👨‍💻@domain.com'
    );

    fails(
        (new Email())->preventSpoofing()->rfcCompliant(),
        'test👨‍💻@domain.com',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->preventSpoofing()->rfcCompliant(),
        'test👨‍💻@domain.com',
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    $spoofingEmail = 'admin@exam'."\u{0440}".'le.com';

    passes(
        (new Email())->rfcCompliant(),
        $spoofingEmail
    );

    passes(
        Rule::email()->rfcCompliant(),
        $spoofingEmail
    );

    fails(
        (new Email())->rfcCompliant()->preventSpoofing(),
        $spoofingEmail,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        Rule::email()->rfcCompliant()->preventSpoofing(),
        $spoofingEmail,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );
});

test('macro', function () {
    Email::macro('laravelEmployee', function () {
        return static::default()->rules('ends_with:@laravel.com');
    });

    fails(
        Email::laravelEmployee(),
        'taylor@example.com',
        ['validation.ends_with']
    );

    fails(
        Rule::email()->laravelEmployee(),
        'taylor@example.com',
        ['validation.ends_with']
    );

    passes(
        Email::laravelEmployee(),
        'taylor@laravel.com'
    );

    passes(
        Rule::email()->laravelEmployee(),
        'taylor@laravel.com'
    );
});

test('it can set default using', function () {
    expect(Email::default())->toBeInstanceOf(Email::class);

    $spoofingEmail = 'admin@exam'."\u{0440}".'le.com';

    passes(
        Email::default(),
        $spoofingEmail
    );

    Email::defaults(function () {
        return (new Email())->preventSpoofing();
    });

    fails(
        Email::default(),
        $spoofingEmail,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    Email::defaults(function () {
        return Rule::email()->rfcCompliant();
    });

    passes(
        Email::default(),
        $spoofingEmail
    );

    Email::defaults(function () {
        return Rule::email()->preventSpoofing();
    });

    fails(
        Email::default(),
        $spoofingEmail,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );
});

test('validation messages', function () {
    Email::defaults(function () {
        return Rule::email()->preventSpoofing();
    });

    $spoofingEmail = 'admin@exam'."\u{0440}".'le.com';

    fails(
        Email::default(),
        $spoofingEmail,
        ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.']
    );

    fails(
        rule: Email::default(),
        values: $spoofingEmail,
        expectedMessages: ['The '.EMAIL_RULE_ATTRIBUTE_REPLACED.' must be a valid email address.'],
        customValidationMessage: 'The :attribute must be a valid email address.'
    );

    fails(
        rule: Email::default(),
        values: $spoofingEmail,
        expectedMessages: ['Please check the entered '.EMAIL_RULE_ATTRIBUTE_REPLACED.", it must be a valid email address, {$spoofingEmail} given."],
        customValidationMessage: 'Please check the entered :attribute, it must be a valid email address, :input given.'
    );

    fails(
        rule: Email::default(),
        values: $spoofingEmail,
        expectedMessages: ['Plain text value'],
        customValidationMessage: 'Plain text value'
    );
});

beforeEach(function () {
    $container = WebApp::boot();

    $container->bind('translator', function () {
        $translator = new Translator(
            new ArrayLoader, 'en'
        );

        $translator->addLines([
            'validation.email' => 'The :attribute must be a valid email address.',
        ], 'en');

        return $translator;
    });

    Facade::setFacadeApplication($container);

    (new ValidationServiceProvider($container))->register();
});

afterEach(function () {
    Email::$defaultCallback = null;

    ControlPanel::setInstance(null);

    Facade::clearResolvedInstances();

    Facade::setFacadeApplication(null);

    WebApp::tearDown($this);
});
