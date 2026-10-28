<?php

namespace Venusian\Serve\Tests\View\Blade;

use Mariner\View\ComponentAttributeBag;

test('props are compiled', function () {
    expect($this->compiler->compileString('@props([\'one\' => true, \'two\' => \'string\'])'))->toBe('<?php $attributes ??= new \Mariner\View\ComponentAttributeBag;

$__newAttributes = [];
$__propNames = \Mariner\View\ComponentAttributeBag::extractPropNames(([\'one\' => true, \'two\' => \'string\']));

foreach ($attributes->all() as $__key => $__value) {
    if (in_array($__key, $__propNames)) {
        $$__key = $$__key ?? $__value;
    } else {
        $__newAttributes[$__key] = $__value;
    }
}

$attributes = new \Mariner\View\ComponentAttributeBag($__newAttributes);

unset($__propNames);
unset($__newAttributes);

foreach (array_filter(([\'one\' => true, \'two\' => \'string\']), \'is_string\', ARRAY_FILTER_USE_KEY) as $__key => $__value) {
    $$__key = $$__key ?? $__value;
}

$__defined_vars = get_defined_vars();

foreach ($attributes->all() as $__key => $__value) {
    if (array_key_exists($__key, $__defined_vars)) unset($$__key);
}

unset($__defined_vars, $__key, $__value); ?>');
});

test('props are extracted from parent attributes correctly', function () {
    $test1 = $test2 = $test4 = null;

    $attributes = new ComponentAttributeBag(['test1' => 'value1', 'test2' => 'value2', 'test3' => 'value3']);

    $template = $this->compiler->compileString('@props([\'test1\' => \'default\', \'test2\', \'test4\' => \'default\'])');

    ob_start();
    eval(" ?> $template <?php ");
    ob_get_clean();

    expect($test1)->toBe('value1');
    expect($test2)->toBe('value2');
    expect(isset($test3))->toBeFalse();
    expect($test4)->toBe('default');

    expect($attributes->get('test1'))->toBeNull();
    expect($attributes->get('test2'))->toBeNull();
    expect($attributes->get('test3'))->toBe('value3');
});
