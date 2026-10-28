<?php

namespace Venusian\Serve\Tests\View;

use Mariner\View\ComponentAttributeBag;

test('attribute retrieval', function () {
    $bag = new ComponentAttributeBag(['class' => 'font-bold', 'name' => 'test']);

    expect((string) $bag->whereStartsWith('class'))->toBe('class="font-bold"');
    expect((string) $bag->whereStartsWith('class')->first())->toBe('font-bold');
    expect((string) $bag->whereDoesntStartWith('class'))->toBe('name="test"');
    expect((string) $bag->whereDoesntStartWith('class')->first())->toBe('test');
    expect((string) $bag->merge(['class' => 'mt-4']))->toBe('class="mt-4 font-bold" name="test"');
    expect((string) $bag->merge(['class' => 'mt-4', 'name' => 'foo']))->toBe('class="mt-4 font-bold" name="test"');
    expect((string) $bag->merge(['class' => 'mt-4', 'id' => 'bar']))->toBe('class="mt-4 font-bold" id="bar" name="test"');
    expect((string) $bag(['class' => 'mt-4']))->toBe('class="mt-4 font-bold" name="test"');
    expect((string) $bag->only('class')->merge(['class' => 'mt-4']))->toBe('class="mt-4 font-bold"');
    expect((string) $bag->merge(['name' => 'default']))->toBe('name="test" class="font-bold"');
    expect((string) $bag->merge([]))->toBe('class="font-bold" name="test"');
    expect((string) $bag->merge(['class' => 'mt-4'])->only('class'))->toBe('class="mt-4 font-bold"');
    expect((string) $bag->only('class')(['class' => 'mt-4']))->toBe('class="mt-4 font-bold"');
    expect($bag->get('class'))->toBe('font-bold');
    expect($bag->get('foo', 'bar'))->toBe('bar');
    expect($bag['class'])->toBe('font-bold');
    expect((string) $bag->class('mt-4'))->toBe('class="mt-4 font-bold" name="test"');
    expect((string) $bag->class(['mt-4']))->toBe('class="mt-4 font-bold" name="test"');
    expect((string) $bag->class(['mt-4', 'ml-2' => true, 'mr-2' => false]))->toBe('class="mt-4 ml-2 font-bold" name="test"');

    $bag = new ComponentAttributeBag(['class' => 'font-bold', 'name' => 'test', 'style' => 'margin-top: 10px']);
    expect((string) $bag->class(['mt-4', 'ml-2' => true, 'mr-2' => false]))->toBe('class="mt-4 ml-2 font-bold" style="margin-top: 10px;" name="test"');
    expect((string) $bag->style(['margin-top: 4px', 'margin-left: 10px;']))->toBe('style="margin-top: 4px; margin-left: 10px; margin-top: 10px;" class="font-bold" name="test"');

    $bag = new ComponentAttributeBag(['class' => 'font-bold', 'name' => 'test', 'style' => 'margin-top: 10px; font-weight: bold']);
    expect((string) $bag->class(['mt-4', 'ml-2' => true, 'mr-2' => false]))->toBe('class="mt-4 ml-2 font-bold" style="margin-top: 10px; font-weight: bold;" name="test"');
    expect((string) $bag->style(['margin-top: 4px', 'margin-left: 10px;']))->toBe('style="margin-top: 4px; margin-left: 10px; margin-top: 10px; font-weight: bold;" class="font-bold" name="test"');

    $bag = new ComponentAttributeBag([]);

    expect((string) $bag->merge(['class' => 'mt-4']))->toBe('class="mt-4"');

    $bag = new ComponentAttributeBag([
        'test-string' => 'ok',
        'test-null' => null,
        'test-false' => false,
        'test-true' => true,
        'test-0' => 0,
        'test-0-string' => '0',
        'test-empty-string' => '',
    ]);

    expect((string) $bag)->toBe('test-string="ok" test-true="test-true" test-0="0" test-0-string="0" test-empty-string=""');
    expect((string) $bag->merge())->toBe('test-string="ok" test-true="test-true" test-0="0" test-0-string="0" test-empty-string=""');

    $bag = (new ComponentAttributeBag)
        ->merge([
            'test-escaped' => '<tag attr="attr">',
        ]);

    expect((string) $bag)->toBe('test-escaped="&lt;tag attr=&quot;attr&quot;&gt;"');

    $bag = (new ComponentAttributeBag)
        ->merge([
            'test-string' => 'ok',
            'test-null' => null,
            'test-false' => false,
            'test-true' => true,
            'test-0' => 0,
            'test-0-string' => '0',
            'test-empty-string' => '',
        ]);

    expect((string) $bag)->toBe('test-string="ok" test-true="test-true" test-0="0" test-0-string="0" test-empty-string=""');

    $bag = (new ComponentAttributeBag)
        ->merge([
            'test-extract-1' => 'extracted-1',
            'test-extract-2' => 'extracted-2',
            'test-discard-1' => 'discarded-1',
            'test-discard-2' => 'discarded-2',
        ]);

    expect((string) $bag->exceptProps([
        'test-discard-1',
        'test-discard-2' => 'defaultValue',
    ]))->toBe('test-extract-1="extracted-1" test-extract-2="extracted-2"');

    $bag = (new ComponentAttributeBag)
        ->merge([
            'test-extract-1' => 'extracted-1',
            'test-extract-2' => 'extracted-2',
            'test-discard-1' => 'discarded-1',
            'test-discard-2' => 'discarded-2',
        ]);

    expect((string) $bag->onlyProps([
        'test-extract-1',
        'test-extract-2' => 'defaultValue',
    ]))->toBe('test-extract-1="extracted-1" test-extract-2="extracted-2"');

    // Test only() method
    $bag = new ComponentAttributeBag(['class' => 'font-bold', 'name' => 'test', 'id' => 'my-id']);
    expect($bag->only('class'))->toBeInstanceOf(ComponentAttributeBag::class);
    expect((string) $bag->only('class'))->toBe('class="font-bold"');
    expect((string) $bag->only(['class', 'name']))->toBe('class="font-bold" name="test"');
    expect((string) $bag->only('missing'))->toBe('');
    expect((string) $bag->only(['name', 'missing']))->toBe('name="test"');

    // Test except() method
    expect($bag->except('class'))->toBeInstanceOf(ComponentAttributeBag::class);
    expect((string) $bag->except('class'))->toBe('name="test" id="my-id"');
    expect((string) $bag->except(['class', 'name']))->toBe('id="my-id"');
    expect((string) $bag->except('missing'))->toBe('class="font-bold" name="test" id="my-id"');
    expect((string) $bag->except(['name', 'missing']))->toBe('class="font-bold" id="my-id"');
});

test('merge terminates default style with semicolon', function () {
    $bag = new ComponentAttributeBag(['style' => 'color: blue']);

    // The default style value is not terminated with a semicolon, so one
    // should be added to keep the resulting declaration list valid.
    expect((string) $bag->merge(['style' => 'font-weight: bold']))->toBe('style="font-weight: bold; color: blue;"');

    $bag = new ComponentAttributeBag(['style' => 'color: blue']);

    // A default style value that already ends with a semicolon must not gain a second one.
    expect((string) $bag->merge(['style' => 'font-weight: bold;']))->toBe('style="font-weight: bold; color: blue;"');

    // When no default style is provided the existing value should be returned untouched.
    $bag = new ComponentAttributeBag(['style' => 'color: blue']);

    expect((string) $bag->merge([]))->toBe('style="color: blue;"');
});

test('attribute retrieval using dot notation', function () {
    $bag = new ComponentAttributeBag([
        'data.config' => 'value1',
        'x-on:click.prevent' => 'handler',
        'wire:model.lazy' => 'username',
        '@submit.prevent' => 'submitForm',
        'wire:model.debounce.500ms' => 'search',
    ]);

    expect($bag->has('data'))->toBeFalse();
    expect($bag->has('wire:model.debounce'))->toBeFalse();

    expect($bag->has('data.config'))->toBeTrue();
    expect($bag->has('x-on:click.prevent'))->toBeTrue();
    expect($bag->has('wire:model.lazy'))->toBeTrue();
    expect($bag->has('@submit.prevent'))->toBeTrue();
    expect($bag->has('wire:model.debounce.500ms'))->toBeTrue();

    expect($bag->get('data.config'))->toBe('value1');
    expect($bag->get('x-on:click.prevent'))->toBe('handler');
    expect($bag->get('wire:model.lazy'))->toBe('username');
    expect($bag->get('@submit.prevent'))->toBe('submitForm');
    expect($bag->get('wire:model.debounce.500ms'))->toBe('search');
});

test('it makes an exception for alpine xdata', function () {
    $bag = new ComponentAttributeBag([
        'required' => true,
        'x-data' => true,
    ]);

    expect((string) $bag)->toBe('required="required" x-data=""');
});

test('it makes an exception for livewire wire attributes', function () {
    $bag = new ComponentAttributeBag([
        'wire:loading' => true,
        'wire:loading.remove' => true,
        'wire:poll' => true,
    ]);

    expect((string) $bag)->toBe('wire:loading="" wire:loading.remove="" wire:poll=""');
});

test('attribute existence', function () {
    $bag = new ComponentAttributeBag(['name' => 'test', 'href' => '', 'src' => null]);

    expect($bag->has('src'))->toBeTrue();
    expect($bag->has('href'))->toBeTrue();
    expect($bag->has('name'))->toBeTrue();
    expect($bag->has(['name']))->toBeTrue();
    expect($bag->hasAny(['class', 'name']))->toBeTrue();
    expect($bag->hasAny('class', 'name'))->toBeTrue();
    expect($bag->missing('name'))->toBeFalse();
    expect($bag->has('class'))->toBeFalse();
    expect($bag->has(['class']))->toBeFalse();
    expect($bag->has(['name', 'class']))->toBeFalse();
    expect($bag->has('name', 'class'))->toBeFalse();
    expect($bag->missing('class'))->toBeTrue();
});

test('attribute is empty', function () {
    $bag = new ComponentAttributeBag([]);

    expect((bool) $bag->isEmpty())->toBeTrue();
});

test('attribute is not empty', function () {
    $bag = new ComponentAttributeBag(['name' => 'test']);

    expect((bool) $bag->isNotEmpty())->toBeTrue();
});

test('attribute is array', function () {
    $bag = new ComponentAttributeBag([
        'name' => 'test',
        'class' => 'font-bold',
    ]);

    expect($bag->toArray())->toBeArray();
    expect($bag->toArray())->toEqual(['name' => 'test', 'class' => 'font-bold']);
});

test('filled', function () {
    $bag = new ComponentAttributeBag([
        'name' => 'test',
        'class' => 'font-bold',
        'empty' => '',
        'whitespace' => '   ',
        'zero' => '0',
        'false' => false,
        'null' => null,
    ]);

    expect($bag->filled('name'))->toBeTrue();
    expect($bag->filled('class'))->toBeTrue();
    expect($bag->filled('zero'))->toBeTrue();
    expect($bag->filled('false'))->toBeTrue();
    expect($bag->filled('null'))->toBeFalse();
    expect($bag->filled('empty'))->toBeFalse();
    expect($bag->filled('whitespace'))->toBeFalse();
    expect($bag->filled('nonexistent'))->toBeFalse();

    // Multiple keys
    expect($bag->filled(['name', 'class']))->toBeTrue();
    expect($bag->filled(['name', 'empty']))->toBeFalse();
    expect($bag->filled('name', 'class'))->toBeTrue();
    expect($bag->filled('name', 'empty'))->toBeFalse();
});

test('is not filled', function () {
    $bag = new ComponentAttributeBag([
        'name' => 'test',
        'empty' => '',
        'whitespace' => '   ',
    ]);

    expect($bag->isNotFilled('name'))->toBeFalse();
    expect($bag->isNotFilled('empty'))->toBeTrue();
    expect($bag->isNotFilled('whitespace'))->toBeTrue();
    expect($bag->isNotFilled('nonexistent'))->toBeTrue();

    // Multiple keys - all must be empty
    expect($bag->isNotFilled(['empty', 'whitespace']))->toBeTrue();
    expect($bag->isNotFilled(['name', 'empty']))->toBeFalse();
});

test('any filled', function () {
    $bag = new ComponentAttributeBag([
        'name' => 'test',
        'empty' => '',
        'whitespace' => '   ',
    ]);

    expect($bag->anyFilled(['name', 'empty']))->toBeTrue();
    expect($bag->anyFilled(['empty', 'name']))->toBeTrue();
    expect($bag->anyFilled(['empty', 'whitespace']))->toBeFalse();
    expect($bag->anyFilled('name', 'empty'))->toBeTrue();
    expect($bag->anyFilled('empty', 'whitespace'))->toBeFalse();
});

test('when filled', function () {
    $bag = new ComponentAttributeBag([
        'name' => 'test',
        'empty' => '',
    ]);

    $result = $bag->whenFilled('name', function ($value) {
        return 'callback-'.$value;
    });
    expect($result)->toBe('callback-test');

    $result = $bag->whenFilled('empty', function ($value) {
        return 'callback-'.$value;
    });
    expect($result)->toBe($bag);

    $result = $bag->whenFilled('empty', function ($value) {
        return 'callback-'.$value;
    }, function () {
        return 'default-callback';
    });
    expect($result)->toBe('default-callback');
});

test('when has', function () {
    $bag = new ComponentAttributeBag(['name' => 'test']);

    $result = $bag->whenHas('name', function ($value) {
        return 'callback-'.$value;
    });
    expect($result)->toBe('callback-test');

    $result = $bag->whenHas('missing', function ($value) {
        return 'callback-'.$value;
    });
    expect($result)->toBe($bag);

    $result = $bag->whenHas('missing', function ($value) {
        return 'callback-'.$value;
    }, function () {
        return 'default-callback';
    });
    expect($result)->toBe('default-callback');
});

test('when missing', function () {
    $bag = new ComponentAttributeBag(['name' => 'test']);

    $result = $bag->whenMissing('name', function () {
        return 'callback';
    });
    expect($result)->toBe($bag);

    $result = $bag->whenMissing('missing', function () {
        return 'callback';
    });
    expect($result)->toBe('callback');

    $result = $bag->whenMissing('name', function () {
        return 'callback';
    }, function () {
        return 'default-callback';
    });
    expect($result)->toBe('default-callback');
});

test('string', function () {
    $bag = new ComponentAttributeBag([
        'name' => 'test',
        'empty' => '',
        'number' => 123,
    ]);

    expect($bag->string('name'))->toBeInstanceOf(\Voyager\NutsAndBolts\DataObjects\Stringable::class);
    expect((string) $bag->string('name'))->toBe('test');
    expect((string) $bag->string('empty'))->toBe('');
    expect((string) $bag->string('number'))->toBe('123');
    expect((string) $bag->string('missing', 'default'))->toBe('default');
});

test('boolean', function () {
    $bag = new ComponentAttributeBag([
        'true_string' => 'true',
        'false_string' => 'false',
        'one' => '1',
        'zero' => '0',
        'yes' => 'yes',
        'no' => 'no',
        'on' => 'on',
        'off' => 'off',
    ]);

    expect($bag->boolean('true_string'))->toBeTrue();
    expect($bag->boolean('false_string'))->toBeFalse();
    expect($bag->boolean('one'))->toBeTrue();
    expect($bag->boolean('zero'))->toBeFalse();
    expect($bag->boolean('yes'))->toBeTrue();
    expect($bag->boolean('no'))->toBeFalse();
    expect($bag->boolean('on'))->toBeTrue();
    expect($bag->boolean('off'))->toBeFalse();
    expect($bag->boolean('missing', true))->toBeTrue();
    expect($bag->boolean('missing', false))->toBeFalse();
});

test('integer', function () {
    $bag = new ComponentAttributeBag([
        'number' => '123',
        'float' => '123.45',
        'string' => 'abc',
    ]);

    expect($bag->integer('number'))->toBe(123);
    expect($bag->integer('float'))->toBe(123);
    expect($bag->integer('string'))->toBe(0);
    expect($bag->integer('missing', 42))->toBe(42);
});

test('float', function () {
    $bag = new ComponentAttributeBag([
        'number' => '123',
        'float' => '123.45',
        'string' => 'abc',
    ]);

    expect($bag->float('number'))->toBe(123.0);
    expect($bag->float('float'))->toBe(123.45);
    expect($bag->float('string'))->toBe(0.0);
    expect($bag->float('missing', 42.5))->toBe(42.5);
});

test('exists', function () {
    $bag = new ComponentAttributeBag(['name' => 'test']);

    expect($bag->exists('name'))->toBeTrue();
    expect($bag->exists('missing'))->toBeFalse();
    expect($bag->exists(['name']))->toBeTrue();
    expect($bag->exists(['missing']))->toBeFalse();
});
