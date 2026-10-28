<?php

namespace Venusian\Serve\Tests\View;

use Mariner\View\ComponentAttributeBag;
use Mariner\View\DynamicComponent;
use ReflectionMethod;

test('compile slots excludes default slot', function () {
    $component = new DynamicComponent('alert');

    $method = new ReflectionMethod(DynamicComponent::class, 'compileSlots');
    $result = $method->invoke($component, [
        '__default' => (object) ['attributes' => new ComponentAttributeBag],
        'title' => (object) ['attributes' => new ComponentAttributeBag],
    ]);

    expect($result)->not->toContain('__default');
    expect($result)->toContain('<x-slot name="title"');
    expect($result)->toContain('{{ $title }}');
});

test('compile slots returns empty string when only default slot is present', function () {
    $component = new DynamicComponent('alert');

    $method = new ReflectionMethod(DynamicComponent::class, 'compileSlots');
    $result = $method->invoke($component, [
        '__default' => (object) ['attributes' => new ComponentAttributeBag],
    ]);

    expect($result)->toBe('');
});
