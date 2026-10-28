<?php

namespace Venusian\Serve\Tests\Validation;

use Voyager\Vessel\ControlPanel;
use Mariner\Translation\ArrayLoader;
use Mariner\Translation\Translator;
use Mariner\Validation\Factory;
use Mariner\Validation\PresenceVerifierInterface;
use Mariner\Validation\Validator;
use ReflectionProperty;

afterEach(function () {
    Validator::flushState();
});

test('make method creates valid validator', function () {
    $translator = new Translator(new ArrayLoader, 'en');
    $factory = new Factory($translator);
    $validator = $factory->make(['foo' => 'bar'], ['baz' => 'boom']);
    expect($validator->getTranslator())->toEqual($translator);
    expect($validator->getData())->toEqual(['foo' => 'bar']);
    expect($validator->getRules())->toEqual(['baz' => ['boom']]);

    $presence = new FakePresenceVerifier;
    $noop1 = function () {
        //
    };
    $noop2 = function () {
        //
    };
    $noop3 = function () {
        //
    };
    $factory->extend('foo', $noop1);
    $factory->extendImplicit('implicit', $noop2);
    $factory->extendDependent('dependent', $noop3);
    $factory->replacer('replacer', $noop3);
    $factory->setPresenceVerifier($presence);
    $validator = $factory->make([], []);
    expect($validator->extensions)->toEqual(['foo' => $noop1, 'implicit' => $noop2, 'dependent' => $noop3]);
    expect($validator->replacers)->toEqual(['replacer' => $noop3]);
    expect($validator->getPresenceVerifier())->toEqual($presence);

    $presence = new FakePresenceVerifier;
    $factory->extend('foo', $noop1, 'foo!');
    $factory->extendImplicit('implicit', $noop2, 'implicit!');
    $factory->extendImplicit('dependent', $noop3, 'dependent!');
    $factory->setPresenceVerifier($presence);
    $validator = $factory->make([], []);
    expect($validator->extensions)->toEqual(['foo' => $noop1, 'implicit' => $noop2, 'dependent' => $noop3]);
    expect($validator->fallbackMessages)->toEqual(['foo' => 'foo!', 'implicit' => 'implicit!', 'dependent' => 'dependent!']);
    expect($validator->getPresenceVerifier())->toEqual($presence);
});

test('validate calls validate on the validator', function () {
    $translator = new Translator(new ArrayLoader, 'en');
    $factory = new Factory($translator);

    $validated = $factory->validate(
        ['foo' => 'bar', 'baz' => 'boom'],
        ['foo' => 'required']
    );

    expect($validated)->toEqual(['foo' => 'bar']);
});

test('custom resolver is called', function () {
    unset($_SERVER['__validator.factory']);
    $translator = new Translator(new ArrayLoader, 'en');
    $factory = new Factory($translator);
    $factory->resolver(function ($translator, $data, $rules) {
        $_SERVER['__validator.factory'] = true;

        return new Validator($translator, $data, $rules);
    });
    $validator = $factory->make(['foo' => 'bar'], ['baz' => 'boom']);

    expect($_SERVER['__validator.factory'])->toBeTrue();
    expect($validator->getTranslator())->toEqual($translator);
    expect($validator->getData())->toEqual(['foo' => 'bar']);
    expect($validator->getRules())->toEqual(['baz' => ['boom']]);
    unset($_SERVER['__validator.factory']);
});

test('validate method can be called publicly', function () {
    $translator = new Translator(new ArrayLoader, 'en');
    $factory = new Factory($translator);
    $factory->extend('foo', function ($attribute, $value, $parameters, $validator) {
        return $validator->validateArray($attribute, $value);
    });

    $validator = $factory->make(['bar' => ['baz']], ['bar' => 'foo']);
    expect($validator->passes())->toBeTrue();
});

test('exclude and include unvalidated array keys', function () {
    $translator = new Translator(new ArrayLoader, 'en');

    $factory = new Factory($translator);
    // check the default behaviour.
    $validator1 = $factory->make(['key' => ['val']], ['key' => 'required']);
    expect($validator1->excludeUnvalidatedArrayKeys)->toBeTrue();

    $factory->excludeUnvalidatedArrayKeys();
    $validator2 = $factory->make(['key' => ['val']], ['key' => 'required']);
    expect($validator2->excludeUnvalidatedArrayKeys)->toBeTrue();

    $factory->includeUnvalidatedArrayKeys();
    $validator3 = $factory->make(['key' => ['val']], ['key' => 'required']);
    expect($validator3->excludeUnvalidatedArrayKeys)->toBeFalse();

    // checks it does not switch behaviour automatically.
    $validator4 = $factory->make(['key' => ['val']], ['key' => 'required']);
    expect($validator4->excludeUnvalidatedArrayKeys)->toBeFalse();

    // checks it can switch.
    $factory->excludeUnvalidatedArrayKeys();
    $validator5 = $factory->make(['key' => ['val']], ['key' => 'required']);
    expect($validator5->excludeUnvalidatedArrayKeys)->toBeTrue();

    // checks switching does not affect previously created validator objects.
    expect($validator1->excludeUnvalidatedArrayKeys)->toBeTrue();
    expect($validator2->excludeUnvalidatedArrayKeys)->toBeTrue();
    expect($validator3->excludeUnvalidatedArrayKeys)->toBeFalse();
    expect($validator4->excludeUnvalidatedArrayKeys)->toBeFalse();
});

test('set container', function () {
    $translator = new Translator(new ArrayLoader, 'en');
    $container = new ControlPanel;
    $factory = new Factory($translator);

    expect($factory->getContainer())->toBeNull();

    expect($factory->setContainer($container)->getContainer())->toBe($container);
});

test('fake dns lookups delegates to the validator', function () {
    (new Factory(new Translator(new ArrayLoader, 'en')))->fakeDnsLookups();

    expect((new ReflectionProperty(Validator::class, 'fakeDnsLookups'))->getValue())->toBeTrue();
});

class FakePresenceVerifier implements PresenceVerifierInterface
{
    public function getCount($collection, $column, $value, $excludeId = null, $idColumn = null, array $extra = [])
    {
        return 0;
    }

    public function getMultiCount($collection, $column, array $values, array $extra = [])
    {
        return 0;
    }
}
