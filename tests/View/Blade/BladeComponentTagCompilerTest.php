<?php

namespace Venusian\Serve\Tests\View\Blade;

use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Core\FrameworkCore;
use Mariner\Contracts\View\Factory;
use Voyager\Database\Instrument\Model;
use Voyager\Pagination\AbstractPaginator;
use Mariner\View\Compilers\BladeCompiler;
use Mariner\View\Compilers\ComponentTagCompiler;
use Mariner\View\Component;
use Mariner\View\ComponentAttributeBag;
use InvalidArgumentException;
use Mockery;

require_once __DIR__.'/Fixtures/NamespacedComponents.php';

test('slots can be compiled', function () {
    mockViewFactory();
    $result = compiler()->compileSlots('<x-slot name="foo">
</x-slot>');

    expect(str_replace("\r\n", "\n", trim($result)))->toBe("@slot('foo', null, []) \n".' @endslot');
});

test('inline slots can be compiled', function () {
    mockViewFactory();
    $result = compiler()->compileSlots('<x-slot:foo>
</x-slot>');

    expect(str_replace("\r\n", "\n", trim($result)))->toBe("@slot('foo', null, []) \n".' @endslot');
});

test('dynamic slots can be compiled', function () {
    mockViewFactory();
    $result = compiler()->compileSlots('<x-slot :name="$foo">
</x-slot>');

    expect(str_replace("\r\n", "\n", trim($result)))->toBe("@slot(\$foo, null, []) \n".' @endslot');
});

test('dynamic slots can be compiled with key of objects', function () {
    mockViewFactory();
    $result = compiler()->compileSlots('<x-slot :name="$foo->name">
</x-slot>');

    expect(str_replace("\r\n", "\n", trim($result)))->toBe("@slot(\$foo->name, null, []) \n".' @endslot');
});

test('slots with attributes can be compiled', function () {
    mockViewFactory();
    $result = compiler()->compileSlots('<x-slot name="foo" class="font-bold">
</x-slot>');

    expect(str_replace("\r\n", "\n", trim($result)))->toBe("@slot('foo', null, ['class' => 'font-bold']) \n".' @endslot');
});

test('inline slots with attributes can be compiled', function () {
    mockViewFactory();
    $result = compiler()->compileSlots('<x-slot:foo class="font-bold">
</x-slot>');

    expect(str_replace("\r\n", "\n", trim($result)))->toBe("@slot('foo', null, ['class' => 'font-bold']) \n".' @endslot');
});

test('slots with dynamic attributes can be compiled', function () {
    mockViewFactory();
    $result = compiler()->compileSlots('<x-slot name="foo" :class="$classes">
</x-slot>');

    expect(str_replace("\r\n", "\n", trim($result)))->toBe("@slot('foo', null, ['class' => \Mariner\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\$classes)]) \n".' @endslot');
});

test('slots with class directive can be compiled', function () {
    mockViewFactory();
    $result = compiler()->compileSlots('<x-slot name="foo" @class($classes)>
</x-slot>');

    expect(str_replace("\r\n", "\n", trim($result)))->toBe("@slot('foo', null, ['class' => \Mariner\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\Voyager\NutsAndBolts\DataObjects\Arr::toCssClasses(\$classes))]) \n".' @endslot');
});

test('slots with style directive can be compiled', function () {
    mockViewFactory();
    $result = compiler()->compileSlots('<x-slot name="foo" @style($styles)>
</x-slot>');

    expect(str_replace("\r\n", "\n", trim($result)))->toBe("@slot('foo', null, ['style' => \Mariner\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\Voyager\NutsAndBolts\DataObjects\Arr::toCssStyles(\$styles))]) \n".' @endslot');
});

test('basic component parsing', function () {
    mockViewFactory();

    $result = compiler(['alert' => TestAlertComponent::class])->compileTags('<div><x-alert type="foo" limit="5" @click="foo" wire:click="changePlan(\'{{ $plan }}\')" required x-intersect.margin.-50%.0px="visibleSection = \'profile\'" /><x-alert /></div>');

    expect(trim($result))->toBe("<div>##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestAlertComponent', 'alert', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestAlertComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['type' => 'foo','limit' => '5','@click' => 'foo','wire:click' => 'changePlan(\''.e(\$plan).'\')','required' => true,'x-intersect.margin.-50%.0px' => 'visibleSection = \'profile\'']); ?>\n".
"@endComponentClass##END-COMPONENT-CLASS####BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestAlertComponent', 'alert', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestAlertComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?>\n".
'@endComponentClass##END-COMPONENT-CLASS##</div>');
});

test('nested default component parsing', function () {
    $container = new ControlPanel;
    $app = Mockery::mock(FrameworkCore::class);
    $container->registerInstance(FrameworkCore::class, $app);
    $factory = Mockery::mock(Factory::class);
    $container->registerInstance(Factory::class, $factory);
    $app->expects('getNamespace')->andReturn('App\\');
    ControlPanel::setInstance($container);

    $result = compiler()->compileTags('<div><x-card /></div>');

    expect(trim($result))->toBe("<div>##BEGIN-COMPONENT-CLASS##@component('App\View\Components\Card\Card', 'card', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\App\View\Components\Card\Card::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?>\n".
        '@endComponentClass##END-COMPONENT-CLASS##</div>');
});

test('custom namespace nested default component parsing', function () {
    mockViewFactory();
    $result = compiler(namespaces: ['nightshade' => 'Nightshade\\View\\Components'])->compileTags('<div><x-nightshade::accordion /></div>');

    expect(trim($result))->toBe("<div>##BEGIN-COMPONENT-CLASS##@component('Nightshade\View\Components\Accordion\Accordion', 'nightshade::accordion', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Nightshade\View\Components\Accordion\Accordion::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?>\n".
        '@endComponentClass##END-COMPONENT-CLASS##</div>');
});

test('basic component with empty attributes parsing', function () {
    mockViewFactory();
    $result = compiler(['alert' => TestAlertComponent::class])->compileTags('<div><x-alert type="" limit=\'\' @click="" required /></div>');

    expect(trim($result))->toBe("<div>##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestAlertComponent', 'alert', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestAlertComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['type' => '','limit' => '','@click' => '','required' => true]); ?>\n".
'@endComponentClass##END-COMPONENT-CLASS##</div>');
});

test('data camel casing', function () {
    mockViewFactory();
    $result = compiler(['profile' => TestProfileComponent::class])->compileTags('<x-profile user-id="1"></x-profile>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestProfileComponent', 'profile', ['userId' => '1'])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestProfileComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?> @endComponentClass##END-COMPONENT-CLASS##");
});

test('colon data', function () {
    mockViewFactory();
    $result = compiler(['profile' => TestProfileComponent::class])->compileTags('<x-profile :user-id="1"></x-profile>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestProfileComponent', 'profile', ['userId' => 1])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestProfileComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?> @endComponentClass##END-COMPONENT-CLASS##");
});

test('colon data short syntax', function () {
    mockViewFactory();
    $result = compiler(['profile' => TestProfileComponent::class])->compileTags('<x-profile :$userId></x-profile>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestProfileComponent', 'profile', ['userId' => \$userId])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestProfileComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?> @endComponentClass##END-COMPONENT-CLASS##");
});

test('colon data with static class property', function () {
    mockViewFactory();
    $result = compiler(['profile' => TestProfileComponent::class])->compileTags('<x-profile :userId="User::$id"></x-profile>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestProfileComponent', 'profile', ['userId' => User::\$id])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestProfileComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?> @endComponentClass##END-COMPONENT-CLASS##");
});

test('colon data with static class property and multiple attributes', function () {
    mockViewFactory();
    $result = compiler(['input' => TestInputComponent::class])->compileTags('<x-input :label="Input::$label" :$name value="Joe"></x-input>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestInputComponent', 'input', ['label' => Input::\$label,'name' => \$name,'value' => 'Joe'])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestInputComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?> @endComponentClass##END-COMPONENT-CLASS##");

    $result = compiler(['input' => TestInputComponent::class])->compileTags('<x-input value="Joe" :$name :label="Input::$label"></x-input>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestInputComponent', 'input', ['value' => 'Joe','name' => \$name,'label' => Input::\$label])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestInputComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?> @endComponentClass##END-COMPONENT-CLASS##");
});

test('self closing component with colon data short syntax', function () {
    mockViewFactory();
    $result = compiler(['profile' => TestProfileComponent::class])->compileTags('<x-profile :$userId/>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestProfileComponent', 'profile', ['userId' => \$userId])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestProfileComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?>\n".
'@endComponentClass##END-COMPONENT-CLASS##');
});

test('self closing component with colon data and static class property short syntax', function () {
    mockViewFactory();
    $result = compiler(['profile' => TestProfileComponent::class])->compileTags('<x-profile :userId="User::$id"/>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestProfileComponent', 'profile', ['userId' => User::\$id])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestProfileComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?>\n".
'@endComponentClass##END-COMPONENT-CLASS##');
});

test('self closing component with colon data multiple attributes and static class property short syntax', function () {
    mockViewFactory();
    $result = compiler(['input' => TestInputComponent::class])->compileTags('<x-input :label="Input::$label" value="Joe" :$name />');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestInputComponent', 'input', ['label' => Input::\$label,'value' => 'Joe','name' => \$name])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestInputComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?>\n".
'@endComponentClass##END-COMPONENT-CLASS##');

    $result = compiler(['input' => TestInputComponent::class])->compileTags('<x-input :$name :label="Input::$label" value="Joe" />');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestInputComponent', 'input', ['name' => \$name,'label' => Input::\$label,'value' => 'Joe'])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestInputComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?>\n".
'@endComponentClass##END-COMPONENT-CLASS##');
});

test('escaped colon attribute', function () {
    mockViewFactory();
    $result = compiler(['profile' => TestProfileComponent::class])->compileTags('<x-profile :user-id="1" ::title="user.name"></x-profile>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestProfileComponent', 'profile', ['userId' => 1])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestProfileComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([':title' => 'user.name']); ?> @endComponentClass##END-COMPONENT-CLASS##");
});

test('colon attributes is escaped if strings', function () {
    mockViewFactory();
    $result = compiler(['profile' => TestProfileComponent::class])->compileTags('<x-profile :src="\'foo\'"></x-profile>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestProfileComponent', 'profile', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestProfileComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['src' => \Mariner\View\Compilers\BladeCompiler::sanitizeComponentAttribute('foo')]); ?> @endComponentClass##END-COMPONENT-CLASS##");
});

test('class directive', function () {
    mockViewFactory();
    $result = compiler(['profile' => TestProfileComponent::class])->compileTags('<x-profile @class(["bar"=>true])></x-profile>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestProfileComponent', 'profile', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestProfileComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['class' => \Mariner\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\Voyager\NutsAndBolts\DataObjects\Arr::toCssClasses(['bar'=>true]))]); ?> @endComponentClass##END-COMPONENT-CLASS##");
});

test('style directive', function () {
    mockViewFactory();
    $result = compiler(['profile' => TestProfileComponent::class])->compileTags('<x-profile @style(["bar"=>true])></x-profile>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestProfileComponent', 'profile', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestProfileComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['style' => \Mariner\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\Voyager\NutsAndBolts\DataObjects\Arr::toCssStyles(['bar'=>true]))]); ?> @endComponentClass##END-COMPONENT-CLASS##");
});

test('colon nested component parsing', function () {
    mockViewFactory();
    $result = compiler(['foo:alert' => TestAlertComponent::class])->compileTags('<x-foo:alert></x-foo:alert>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestAlertComponent', 'foo:alert', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestAlertComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?> @endComponentClass##END-COMPONENT-CLASS##");
});

test('colon starting nested component parsing', function () {
    mockViewFactory();
    $result = compiler(['foo:alert' => TestAlertComponent::class])->compileTags('<x:foo:alert></x-foo:alert>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestAlertComponent', 'foo:alert', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestAlertComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?> @endComponentClass##END-COMPONENT-CLASS##");
});

test('self closing components can be compiled', function () {
    mockViewFactory();
    $result = compiler(['alert' => TestAlertComponent::class])->compileTags('<div><x-alert/></div>');

    expect(trim($result))->toBe("<div>##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestAlertComponent', 'alert', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestAlertComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?>\n".
'@endComponentClass##END-COMPONENT-CLASS##</div>');
});

test('classes can be found by components', function () {
    mockViewFactory();
    $compiler = compiler(namespaces: ['nightshade' => 'Nightshade\\View\\Components']);

    $result = $compiler->findClassByComponent('nightshade::calendar');
    expect(trim($result))->toBe('Nightshade\\View\\Components\\Calendar');

    $result = $compiler->findClassByComponent('nightshade::accordion');
    expect(trim($result))->toBe('Nightshade\\View\\Components\\Accordion\\Accordion');
});

test('class names can be guessed', function () {
    $container = new ControlPanel;
    $app = Mockery::mock(FrameworkCore::class);
    $app->expects('getNamespace')->andReturn('App\\');
    $container->registerInstance(FrameworkCore::class, $app);
    ControlPanel::setInstance($container);

    $result = compiler()->guessClassName('alert');

    expect(trim($result))->toBe("App\View\Components\Alert");

    ControlPanel::setInstance(null);
});

test('class names can be guessed with namespaces', function () {
    $container = new ControlPanel;
    $app = Mockery::mock(FrameworkCore::class);
    $container->registerInstance(FrameworkCore::class, $app);
    $app->expects('getNamespace')->andReturn('App\\');
    ControlPanel::setInstance($container);

    $result = compiler()->guessClassName('base.alert');

    expect(trim($result))->toBe("App\View\Components\Base\Alert");

    ControlPanel::setInstance(null);
});

test('components can be compiled with hyphen attributes', function () {
    mockViewFactory();

    $result = compiler(['alert' => TestAlertComponent::class])->compileTags('<x-alert class="bar" wire:model="foo" x-on:click="bar" @click="baz" />');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestAlertComponent', 'alert', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestAlertComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['class' => 'bar','wire:model' => 'foo','x-on:click' => 'bar','@click' => 'baz']); ?>\n".
'@endComponentClass##END-COMPONENT-CLASS##');
});

test('self closing components can be compiled with data and attributes', function () {
    mockViewFactory();
    $result = compiler(['alert' => TestAlertComponent::class])->compileTags('<x-alert title="foo" class="bar" wire:model="foo" />');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestAlertComponent', 'alert', ['title' => 'foo'])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestAlertComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['class' => 'bar','wire:model' => 'foo']); ?>\n".
'@endComponentClass##END-COMPONENT-CLASS##');
});

test('component can receive attribute bag', function () {
    mockViewFactory();

    $result = compiler(['profile' => TestProfileComponent::class])->compileTags('<x-profile class="bar" {{ $attributes }} wire:model="foo"></x-profile>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestProfileComponent', 'profile', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestProfileComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['class' => 'bar','attributes' => \Mariner\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\$attributes),'wire:model' => 'foo']); ?> @endComponentClass##END-COMPONENT-CLASS##");
});

test('self closing component can receive attribute bag', function () {
    mockViewFactory();

    $result = compiler(['alert' => TestAlertComponent::class])->compileTags('<div><x-alert title="foo" class="bar" {{ $attributes->merge([\'class\' => \'test\']) }} wire:model="foo" /></div>');

    expect(trim($result))->toBe("<div>##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestAlertComponent', 'alert', ['title' => 'foo'])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestAlertComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['class' => 'bar','attributes' => \Mariner\View\Compilers\BladeCompiler::sanitizeComponentAttribute(\$attributes->merge(['class' => 'test'])),'wire:model' => 'foo']); ?>\n".
        '@endComponentClass##END-COMPONENT-CLASS##</div>');
});

test('components can have attached word', function () {
    mockViewFactory();
    $result = compiler(['profile' => TestProfileComponent::class])->compileTags('<x-profile></x-profile>Words');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestProfileComponent', 'profile', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestProfileComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?> @endComponentClass##END-COMPONENT-CLASS##Words");
});

test('self closing components can have attached word', function () {
    mockViewFactory();
    $result = compiler(['alert' => TestAlertComponent::class])->compileTags('<x-alert/>Words');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestAlertComponent', 'alert', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestAlertComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?>\n".
'@endComponentClass##END-COMPONENT-CLASS##Words');
});

test('self closing components can be compiled with bound data', function () {
    mockViewFactory();
    $result = compiler(['alert' => TestAlertComponent::class])->compileTags('<x-alert :title="$title" class="bar" />');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestAlertComponent', 'alert', ['title' => \$title])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestAlertComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['class' => 'bar']); ?>\n".
'@endComponentClass##END-COMPONENT-CLASS##');
});

test('paired component tags', function () {
    mockViewFactory();
    $result = compiler(['alert' => TestAlertComponent::class])->compileTags('<x-alert>
</x-alert>');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Venusian\Serve\Tests\View\Blade\TestAlertComponent', 'alert', [])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Venusian\Serve\Tests\View\Blade\TestAlertComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?>
 @endComponentClass##END-COMPONENT-CLASS##");
});

test('classless components', function () {
    $container = new ControlPanel;
    $app = Mockery::mock(FrameworkCore::class);
    $container->registerInstance(FrameworkCore::class, $app);
    $factory = Mockery::mock(Factory::class);
    $container->registerInstance(Factory::class, $factory);
    $app->expects('getNamespace')->andReturn('App\\');
    $factory->expects('exists')->andReturn(true);
    ControlPanel::setInstance($container);

    $result = compiler()->compileTags('<x-anonymous-component :name="\'Taylor\'" :age="31" wire:model="foo" />');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Mariner\View\AnonymousComponent', 'anonymous-component', ['view' => 'components.anonymous-component','data' => ['name' => 'Taylor','age' => 31,'wire:model' => 'foo']])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Mariner\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['name' => \Mariner\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Taylor'),'age' => 31,'wire:model' => 'foo']); ?>\n".
'@endComponentClass##END-COMPONENT-CLASS##');
});

test('classless components with index view', function () {
    $container = new ControlPanel;
    $app = Mockery::mock(FrameworkCore::class);
    $container->registerInstance(FrameworkCore::class, $app);
    $factory = Mockery::mock(Factory::class);
    $container->registerInstance(Factory::class, $factory);
    $app->expects('getNamespace')->andReturn('App\\');
    $factory->expects('exists')->times(2)->andReturn(false, true);
    ControlPanel::setInstance($container);

    $result = compiler()->compileTags('<x-anonymous-component :name="\'Taylor\'" :age="31" wire:model="foo" />');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Mariner\View\AnonymousComponent', 'anonymous-component', ['view' => 'components.anonymous-component.index','data' => ['name' => 'Taylor','age' => 31,'wire:model' => 'foo']])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Mariner\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['name' => \Mariner\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Taylor'),'age' => 31,'wire:model' => 'foo']); ?>\n".
'@endComponentClass##END-COMPONENT-CLASS##');
});

test('classless components with component view', function () {
    $container = new ControlPanel;
    $app = Mockery::mock(FrameworkCore::class);
    $container->registerInstance(FrameworkCore::class, $app);
    $factory = Mockery::mock(Factory::class);
    $container->registerInstance(Factory::class, $factory);
    $app->expects('getNamespace')->andReturn('App\\');
    $factory->expects('exists')->times(3)->andReturn(false, false, true);
    ControlPanel::setInstance($container);

    $result = compiler()->compileTags('<x-anonymous-component :name="\'Taylor\'" :age="31" wire:model="foo" />');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Mariner\View\AnonymousComponent', 'anonymous-component', ['view' => 'components.anonymous-component.anonymous-component','data' => ['name' => 'Taylor','age' => 31,'wire:model' => 'foo']])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Mariner\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['name' => \Mariner\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Taylor'),'age' => 31,'wire:model' => 'foo']); ?>\n".
        '@endComponentClass##END-COMPONENT-CLASS##');
});

test('packages classless components', function () {
    $container = new ControlPanel;
    $app = Mockery::mock(FrameworkCore::class);
    $container->registerInstance(FrameworkCore::class, $app);
    $factory = Mockery::mock(Factory::class);
    $container->registerInstance(Factory::class, $factory);
    $app->expects('getNamespace')->andReturn('App\\');
    $factory->expects('exists')->andReturn(true);
    ControlPanel::setInstance($container);

    $result = compiler()->compileTags('<x-package::anonymous-component :name="\'Taylor\'" :age="31" wire:model="foo" />');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Mariner\View\AnonymousComponent', 'package::anonymous-component', ['view' => 'package::components.anonymous-component','data' => ['name' => 'Taylor','age' => 31,'wire:model' => 'foo']])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Mariner\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['name' => \Mariner\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Taylor'),'age' => 31,'wire:model' => 'foo']); ?>\n".
'@endComponentClass##END-COMPONENT-CLASS##');
});

test('classless components with anonymous component namespace', function () {
    $container = new ControlPanel;

    $app = Mockery::mock(FrameworkCore::class);
    $container->registerInstance(FrameworkCore::class, $app);
    $factory = Mockery::mock(Factory::class);
    $container->registerInstance(Factory::class, $factory);

    $app->expects('getNamespace')->andReturn('App\\');
    $factory->expects('exists')->times(4)->andReturnUsing(function ($arg) {
        // In our test, we'll do as if the 'public.frontend.anonymous-component'
        // view exists and not the others.
        return $arg === 'public.frontend.anonymous-component';
    });

    ControlPanel::setInstance($container);

    $blade = Mockery::mock(BladeCompiler::class)->makePartial();

    $blade->expects('getAnonymousComponentNamespaces')->andReturn([
        'frontend' => 'public.frontend',
    ]);

    $compiler = compiler([], [], $blade);

    $result = $compiler->compileTags('<x-frontend::anonymous-component :name="\'Taylor\'" :age="31" wire:model="foo" />');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Mariner\View\AnonymousComponent', 'frontend::anonymous-component', ['view' => 'public.frontend.anonymous-component','data' => ['name' => 'Taylor','age' => 31,'wire:model' => 'foo']])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Mariner\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['name' => \Mariner\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Taylor'),'age' => 31,'wire:model' => 'foo']); ?>\n".
        '@endComponentClass##END-COMPONENT-CLASS##');
});

test('classless components with anonymous component namespace with index view', function () {
    $container = new ControlPanel;

    $app = Mockery::mock(FrameworkCore::class);
    $container->registerInstance(FrameworkCore::class, $app);
    $factory = Mockery::mock(Factory::class);
    $container->registerInstance(Factory::class, $factory);

    $app->expects('getNamespace')->andReturn('App\\');
    $factory->expects('exists')->times(5)->andReturnUsing(function (string $viewNameBeingCheckedForExistence) {
        // In our test, we'll do as if the 'public.frontend.anonymous-component'
        // view exists and not the others.
        return $viewNameBeingCheckedForExistence === 'admin.auth.components.anonymous-component.index';
    });

    ControlPanel::setInstance($container);

    $blade = Mockery::mock(BladeCompiler::class)->makePartial();

    $blade->expects('getAnonymousComponentNamespaces')->andReturn([
        'admin.auth' => 'admin.auth.components',
    ]);

    $compiler = compiler([], [], $blade);

    $result = $compiler->compileTags('<x-admin.auth::anonymous-component :name="\'Taylor\'" :age="31" wire:model="foo" />');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Mariner\View\AnonymousComponent', 'admin.auth::anonymous-component', ['view' => 'admin.auth.components.anonymous-component.index','data' => ['name' => 'Taylor','age' => 31,'wire:model' => 'foo']])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Mariner\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['name' => \Mariner\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Taylor'),'age' => 31,'wire:model' => 'foo']); ?>\n".
        '@endComponentClass##END-COMPONENT-CLASS##');
});

test('classless components with anonymous component namespace with component view', function () {
    $container = new ControlPanel;

    $app = Mockery::mock(FrameworkCore::class);
    $container->registerInstance(FrameworkCore::class, $app);
    $factory = Mockery::mock(Factory::class);
    $container->registerInstance(Factory::class, $factory);

    $app->expects('getNamespace')->andReturn('App\\');
    $factory->expects('exists')->times(6)->andReturnUsing(function (string $viewNameBeingCheckedForExistence) {
        // In our test, we'll do as if the 'public.frontend.anonymous-component'
        // view exists and not the others.
        return $viewNameBeingCheckedForExistence === 'admin.auth.components.anonymous-component.anonymous-component';
    });

    ControlPanel::setInstance($container);

    $blade = Mockery::mock(BladeCompiler::class)->makePartial();

    $blade->expects('getAnonymousComponentNamespaces')->andReturn([
        'admin.auth' => 'admin.auth.components',
    ]);

    $compiler = compiler([], [], $blade);

    $result = $compiler->compileTags('<x-admin.auth::anonymous-component :name="\'Taylor\'" :age="31" wire:model="foo" />');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Mariner\View\AnonymousComponent', 'admin.auth::anonymous-component', ['view' => 'admin.auth.components.anonymous-component.anonymous-component','data' => ['name' => 'Taylor','age' => 31,'wire:model' => 'foo']])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Mariner\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes(['name' => \Mariner\View\Compilers\BladeCompiler::sanitizeComponentAttribute('Taylor'),'age' => 31,'wire:model' => 'foo']); ?>\n".
        '@endComponentClass##END-COMPONENT-CLASS##');
});

test('classless components with anonymous component path', function () {
    $container = new ControlPanel;

    $app = Mockery::mock(FrameworkCore::class);
    $container->registerInstance(FrameworkCore::class, $app);
    $factory = Mockery::mock(Factory::class);
    $container->registerInstance(Factory::class, $factory);

    $app->expects('getNamespace')->andReturn('App\\');

    $factory->expects('exists')->times(5)->andReturnUsing(function ($arg) {
        return $arg === hash('xxh128', 'test-directory').'::panel.index';
    });

    ControlPanel::setInstance($container);

    $blade = Mockery::mock(BladeCompiler::class)->makePartial();

    $blade->expects('getAnonymousComponentPaths')->andReturn([
        ['path' => 'test-directory', 'prefix' => null, 'prefixHash' => hash('xxh128', 'test-directory')],
    ]);

    $compiler = compiler([], [], $blade);

    $result = $compiler->compileTags('<x-panel />');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Mariner\View\AnonymousComponent', 'panel', ['view' => '".hash('xxh128', 'test-directory')."::panel.index','data' => []])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Mariner\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?>\n".
        '@endComponentClass##END-COMPONENT-CLASS##');
});

test('classless components with anonymous component path component name', function () {
    $container = new ControlPanel;

    $app = Mockery::mock(FrameworkCore::class);
    $container->registerInstance(FrameworkCore::class, $app);
    $factory = Mockery::mock(Factory::class);
    $container->registerInstance(Factory::class, $factory);

    $app->expects('getNamespace')->andReturn('App\\');

    $factory->expects('exists')->times(6)->andReturnUsing(function ($arg) {
        return $arg === md5('test-directory').'::panel.panel';
    });

    ControlPanel::setInstance($container);

    $blade = Mockery::mock(BladeCompiler::class)->makePartial();

    $blade->expects('getAnonymousComponentPaths')->andReturn([
        ['path' => 'test-directory', 'prefix' => null, 'prefixHash' => md5('test-directory')],
    ]);

    $compiler = compiler([], [], $blade);

    $result = $compiler->compileTags('<x-panel />');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Mariner\View\AnonymousComponent', 'panel', ['view' => '".md5('test-directory')."::panel.panel','data' => []])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Mariner\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?>\n".
        '@endComponentClass##END-COMPONENT-CLASS##');
});

test('classless index components with anonymous component path', function () {
    $container = new ControlPanel;

    $app = Mockery::mock(FrameworkCore::class);
    $container->registerInstance(FrameworkCore::class, $app);
    $factory = Mockery::mock(Factory::class);
    $container->registerInstance(Factory::class, $factory);

    $app->expects('getNamespace')->andReturn('App\\');

    $factory->expects('exists')->times(4)->andReturnUsing(function ($arg) {
        return $arg === hash('xxh128', 'test-directory').'::panel';
    });

    ControlPanel::setInstance($container);

    $blade = Mockery::mock(BladeCompiler::class)->makePartial();

    $blade->expects('getAnonymousComponentPaths')->andReturn([
        ['path' => 'test-directory', 'prefix' => null, 'prefixHash' => hash('xxh128', 'test-directory')],
    ]);

    $compiler = compiler([], [], $blade);

    $result = $compiler->compileTags('<x-panel />');

    expect(trim($result))->toBe("##BEGIN-COMPONENT-CLASS##@component('Mariner\View\AnonymousComponent', 'panel', ['view' => '".hash('xxh128', 'test-directory')."::panel','data' => []])
<?php if (isset(\$attributes) && \$attributes instanceof Mariner\View\ComponentAttributeBag): ?>
<?php \$attributes = \$attributes->except(\Mariner\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php \$component->withAttributes([]); ?>\n".
        '@endComponentClass##END-COMPONENT-CLASS##');
});

test('attribute sanitization', function () {
    mockViewFactory();
    $class = new class
    {
        public function __toString()
        {
            return '<hi>';
        }
    };

    $model = new class extends Model {
    };

    $paginator = new class extends AbstractPaginator {
    };

    expect(BladeCompiler::sanitizeComponentAttribute('<hi>'))->toEqual(e('<hi>'));
    expect(BladeCompiler::sanitizeComponentAttribute('1'))->toEqual(e('1'));
    expect(BladeCompiler::sanitizeComponentAttribute(1))->toEqual(1);
    expect(BladeCompiler::sanitizeComponentAttribute($class))->toEqual(e('<hi>'));
    expect(BladeCompiler::sanitizeComponentAttribute($model))->toBe($model);
    expect(BladeCompiler::sanitizeComponentAttribute($paginator))->toBe($paginator);
});

test('it throws an exception for non existing aliases', function () {
    mockViewFactory(false);

    compiler(['alert' => 'foo.bar'])->compileTags('<x-alert />');
})->throws(InvalidArgumentException::class);

test('it throws an exception for non existing class', function () {
    $container = new ControlPanel;
    $app = Mockery::mock(FrameworkCore::class);
    $container->registerInstance(FrameworkCore::class, $app);
    $factory = Mockery::mock(Factory::class);
    $container->registerInstance(Factory::class, $factory);
    $app->expects('getNamespace')->andReturn('App\\');
    $factory->expects('exists')->times(3)->andReturn(false);
    ControlPanel::setInstance($container);

    compiler()->compileTags('<x-alert />');
})->throws(InvalidArgumentException::class);

test('attributes treated as props are removed from final attributes', function () {
    $container = new ControlPanel;
    $app = Mockery::mock(FrameworkCore::class);
    $container->registerInstance(FrameworkCore::class, $app);
    $factory = Mockery::mock(Factory::class);
    $container->registerInstance(Factory::class, $factory);
    $container->alias(Factory::class, 'view');
    $app->shouldReceive('getNamespace')->never()->andReturn('App\\');
    $factory->shouldReceive('exists')->never();

    ControlPanel::setInstance($container);

    $attributes = new ComponentAttributeBag(['userId' => 'bar', 'other' => 'ok']);

    $component = Mockery::mock(Component::class);
    $component->expects('withName')->with('profile');
    $component->expects('shouldRender')->andReturn(true);
    $component->expects('resolveView')->andReturn('');
    $component->expects('data')->andReturn([]);
    $component->expects('withAttributes')->with(['attributes' => new ComponentAttributeBag(['other' => 'ok'])]);

    Component::resolveComponentsUsing(fn () => $component);

    $__env = Mockery::mock(\Mariner\View\Factory::class);
    $__env->expects('startComponent');
    $__env->expects('renderComponent');

    $template = compiler(['profile' => TestProfileComponent::class])->compileTags('<x-profile {{ $attributes }} />');
    $template = $this->compiler->compileString($template);

    ob_start();
    eval(" ?> $template <?php ");
    ob_get_clean();

    expect($attributes->get('userId'))->toBe('bar');
    expect($attributes->get('other'))->toBe('ok');
});

test('original attributes are restored after rendering child component with props', function () {
    $container = new ControlPanel;
    $app = Mockery::mock(FrameworkCore::class);
    $container->registerInstance(FrameworkCore::class, $app);
    $factory = Mockery::mock(Factory::class);
    $container->registerInstance(Factory::class, $factory);
    $container->alias(Factory::class, 'view');
    $app->shouldReceive('getNamespace')->never()->andReturn('App\\');
    $factory->shouldReceive('exists')->never();

    ControlPanel::setInstance($container);

    $attributes = new ComponentAttributeBag(['userId' => 'bar', 'other' => 'ok']);

    $containerComponent = Mockery::mock(Component::class);
    $containerComponent->expects('withName')->with('container');
    $containerComponent->expects('shouldRender')->andReturn(true);
    $containerComponent->expects('resolveView')->andReturn('');
    $containerComponent->expects('data')->andReturn([]);
    $containerComponent->expects('withAttributes');

    $profileComponent = Mockery::mock(Component::class);
    $profileComponent->expects('withName')->with('profile');
    $profileComponent->expects('shouldRender')->andReturn(true);
    $profileComponent->expects('resolveView')->andReturn('');
    $profileComponent->expects('data')->andReturn([]);
    $profileComponent->expects('withAttributes')->with(['attributes' => new ComponentAttributeBag(['other' => 'ok'])]);

    Component::resolveComponentsUsing(fn ($component) => match ($component) {
        TestContainerComponent::class => $containerComponent,
        TestProfileComponent::class => $profileComponent,
    });

    $__env = Mockery::mock(\Mariner\View\Factory::class);
    $__env->expects('startComponent')->times(2);
    $__env->expects('renderComponent')->times(2);

    $template = compiler([
        'container' => TestContainerComponent::class,
        'profile' => TestProfileComponent::class,
    ])->compileTags('<x-container><x-profile {{ $attributes }} /></x-container>');
    $template = $this->compiler->compileString($template);

    ob_start();
    eval(" ?> $template <?php ");
    ob_get_clean();

    expect($attributes->get('userId'))->toBe('bar');
    expect($attributes->get('other'))->toBe('ok');
});

function mockViewFactory($existsSucceeds = true)
{
    $container = new ControlPanel;
    $factory = Mockery::mock(Factory::class);
    $container->registerInstance(Factory::class, $factory);
    $container->alias(Factory::class, 'view');
    $factory->shouldReceive('exists')->andReturn($existsSucceeds);
    ControlPanel::setInstance($container);
}

function compiler(array $aliases = [], array $namespaces = [], ?BladeCompiler $blade = null)
{
    return new ComponentTagCompiler(
        $aliases, $namespaces, $blade
    );
}

class TestAlertComponent extends Component
{
    public $title;

    public function __construct($title = 'foo', $userId = 1)
    {
        $this->title = $title;
    }

    public function render()
    {
        return 'alert';
    }
}

class TestProfileComponent extends Component
{
    public $userId;

    public function __construct($userId = 'foo')
    {
        $this->userId = $userId;
    }

    public function render()
    {
        return 'profile';
    }
}

class TestInputComponent extends Component
{
    public $userId;

    public function __construct($name, $label, $value)
    {
        $this->name = $name;
        $this->label = $label;
        $this->value = $value;
    }

    public function render()
    {
        return 'input';
    }
}

class TestContainerComponent extends Component
{
    public function render()
    {
        return 'container';
    }
}
