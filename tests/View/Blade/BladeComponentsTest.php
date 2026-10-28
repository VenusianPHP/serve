<?php

namespace Venusian\Serve\Tests\View\Blade;

use Mariner\View\Component;
use Mariner\View\ComponentAttributeBag;
use Mockery;

test('components are compiled', function () {
    expect($this->compiler->compileString('@component(\'foo\', ["foo" => "bar"])'))->toBe('<?php $__env->startComponent(\'foo\', ["foo" => "bar"]); ?>');
    expect($this->compiler->compileString('@component(\'foo\')'))->toBe('<?php $__env->startComponent(\'foo\'); ?>');
});

test('class components are compiled', function () {
    expect($this->compiler->compileString('@component(\'Venusian\Serve\Tests\View\Blade\ComponentStub::class\', \'test\', ["foo" => "bar"])'))->toBe(str_replace("\r\n", "\n", '<?php if (isset($component)) { $__componentOriginalfe594626525d8c129e4980ecaaaf32da = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginalfe594626525d8c129e4980ecaaaf32da = $attributes; } ?>
<?php $component = Venusian\Serve\Tests\View\Blade\ComponentStub::class::resolve(["foo" => "bar"] + (isset($attributes) && $attributes instanceof Mariner\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName(\'test\'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>'));
});

test('end components are compiled', function () {
    $this->compiler::newComponentHash('foo');

    expect($this->compiler->compileString('@endcomponent'))->toBe('<?php echo $__env->renderComponent(); ?>');
});

test('end component classes are compiled', function () {
    $this->compiler::newComponentHash('foo');

    expect($this->compiler->compileString('@endcomponentClass'))->toBe(str_replace("\r\n", "\n", '<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal79aef92e83454121ab6e5f64077e7d8a)): ?>
<?php $attributes = $__attributesOriginal79aef92e83454121ab6e5f64077e7d8a; ?>
<?php unset($__attributesOriginal79aef92e83454121ab6e5f64077e7d8a); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal79aef92e83454121ab6e5f64077e7d8a)): ?>
<?php $component = $__componentOriginal79aef92e83454121ab6e5f64077e7d8a; ?>
<?php unset($__componentOriginal79aef92e83454121ab6e5f64077e7d8a); ?>
<?php endif; ?>'));
});

test('slots are compiled', function () {
    expect($this->compiler->compileString('@slot(\'foo\', null, ["foo" => "bar"])'))->toBe('<?php $__env->slot(\'foo\', null, ["foo" => "bar"]); ?>');
    expect($this->compiler->compileString('@slot(\'foo\')'))->toBe('<?php $__env->slot(\'foo\'); ?>');
});

test('end slots are compiled', function () {
    expect($this->compiler->compileString('@endslot'))->toBe('<?php $__env->endSlot(); ?>');
});

test('props are extracted from parent attributes correctly for class components', function () {
    $attributes = new ComponentAttributeBag(['foo' => 'baz', 'other' => 'ok']);

    $component = Mockery::mock(Component::class);
    $component->shouldReceive('withName', 'test');
    $component->expects('shouldRender')->andReturn(false);

    Component::resolveComponentsUsing(fn () => $component);

    $template = $this->compiler->compileString('@component(\'Venusian\Serve\Tests\View\Blade\ComponentStub::class\', \'test\', ["foo" => "bar"])');

    ob_start();
    eval(" ?> $template <?php endif; ");
    ob_get_clean();
});

class ComponentStub extends Component
{
    public function render()
    {
        return '';
    }
}
