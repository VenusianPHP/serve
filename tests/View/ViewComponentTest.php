<?php

namespace Venusian\Serve\Tests\View;

use Mariner\View\Component;
use Mariner\View\ComponentAttributeBag;
use Mariner\View\ComponentSlot;
use ReflectionMethod;

test('data exposure', function () {
    $component = new TestViewComponent;

    $variables = $component->data();

    expect($variables['votes'])->toEqual(10);
    expect($variables['hello']())->toBe('world');
    expect($variables['hello']('taylor'))->toBe('taylor');
});

test('ignored methods are not exposed to view data', function () {
    $component = new class extends Component
    {
        protected $except = ['goodbye'];

        public function render()
        {
            return 'test';
        }

        public function hello()
        {
            return 'hello world';
        }

        public function goodbye()
        {
            return 'goodbye';
        }
    };

    $data = $component->data();

    expect($data)->toHaveKey('hello');
    expect($data)->not->toHaveKey('goodbye');

    $reflectionMethod = new ReflectionMethod($component, 'ignoredMethods');

    $ignoredMethods = $reflectionMethod->invoke($component);

    foreach ($ignoredMethods as $method) {
        expect($data)->not->toHaveKey($method);
    }
});

test('attribute parent inheritance', function () {
    $component = new TestViewComponent;
    $attributes = new ComponentAttributeBag(['class' => 'bar', 'type' => 'button']);

    $component->withAttributes(['class' => 'foo', 'attributes' => $attributes]);

    expect((string) $component->attributes)->toBe('class="foo bar" type="button"');

    // Test overriding parent class attributes
    $component->withAttributes(['class' => 'override', 'type' => 'submit']);
    expect((string) $component->attributes)->toBe('class="override" type="submit"');
});

test('slot attribute parent inheritance', function () {
    $attributes = new ComponentAttributeBag(['class' => 'bar', 'type' => 'button']);

    $slot = new ComponentSlot('test', [
        'class' => 'foo',
        'attributes' => $attributes,
    ]);

    expect((string) $slot->attributes)->toBe('class="foo bar" type="button"');

    // Test overriding parent class attributes
    $slot->withAttributes(['class' => 'override', 'type' => 'submit']);
    expect((string) $slot->attributes)->toBe('class="override" type="submit"');
});

test('public methods with no args are converted to stringable callables invoked and not cached', function () {
    $component = new TestSampleViewComponent;

    expect($component->counter)->toEqual(0);
    expect(TestSampleViewComponent::$publicStaticCounter)->toEqual(0);
    $variables = $component->data();
    expect($component->counter)->toEqual(0);
    expect(TestSampleViewComponent::$publicStaticCounter)->toEqual(0);

    expect($variables['noArgs']())->toBe('noArgs val');
    expect((string) $variables['noArgs'])->toBe('noArgs val');
    expect($variables['counter'])->toEqual(0);

    // make sure non-public members are not invoked nor counted.
    expect($component->counter)->toEqual(2);
    expect($variables)->toHaveKey('publicHello');
    expect($variables)->not->toHaveKey('protectedHello');
    expect($variables)->not->toHaveKey('privateHello');

    expect($variables)->not->toHaveKey('publicStaticCounter');
    expect($variables)->not->toHaveKey('protectedCounter');
    expect($variables)->not->toHaveKey('privateCounter');

    // test each time we invoke data(), the non-argument methods aren't invoked
    expect($component->counter)->toEqual(2);
    $component->data();
    expect($component->counter)->toEqual(2);
    $component->data();
    expect($component->counter)->toEqual(2);
});

test('it ignores excepted methods and properties', function () {
    $component = new TestExceptedViewComponent;
    $variables = $component->data();

    // Ignored methods (with no args) are not invoked behind the scenes.
    expect($component->taylor)->toBe('Otwell');

    expect($variables)->not->toHaveKey('hello');
    expect($variables)->not->toHaveKey('hello2');
    expect($variables)->not->toHaveKey('taylor');
});

test('methods override property values', function () {
    $component = new TestHelloPropertyHelloMethodComponent;
    $variables = $component->data();
    expect($variables)->toHaveKey('hello');
    expect($variables['hello']())->toBe('world');

    // protected methods do not override public properties.
    expect($variables)->toHaveKey('world');
    expect($variables['world'])->toBe('world property');
});

class TestViewComponent extends Component
{
    public $votes = 10;

    public function render()
    {
        return 'test';
    }

    public function hello($string = 'world')
    {
        return $string;
    }
}

class TestSampleViewComponent extends Component
{
    public $counter = 0;

    public static $publicStaticCounter = 0;

    protected $protectedCounter = 0;

    private $privateCounter = 0;

    public function render()
    {
        return 'test';
    }

    public function publicHello($string = 'world')
    {
        $this->counter = 100;

        return $string;
    }

    public function noArgs()
    {
        $this->counter++;

        return 'noArgs val';
    }

    protected function protectedHello()
    {
        $this->counter++;
    }

    private function privateHello()
    {
        $this->counter++;
    }
}

class TestExceptedViewComponent extends Component
{
    protected $except = ['hello', 'hello2', 'taylor'];

    public $taylor = 'Otwell';

    public function hello($string = 'world')
    {
        return $string;
    }

    public function hello2()
    {
        return $this->taylor = '';
    }

    public function render()
    {
        return 'test';
    }
}

class TestHelloPropertyHelloMethodComponent extends Component
{
    public function render()
    {
        return 'test';
    }

    public $hello = 'hello property';

    public $world = 'world property';

    public function hello($string = 'world')
    {
        return $string;
    }

    protected function world($string = 'world')
    {
        return $string;
    }
}
