<?php

namespace Venusian\Serve\Tests\View;

use Closure;
use Voyager\Config\Repository as Config;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Vessel\DataBindingException;
use Voyager\Contracts\NutsAndBolts\Htmlable;
use Mariner\Contracts\View\Factory as FactoryContract;
use Mariner\NutsAndBolts\Facades\Facade;
use Voyager\NutsAndBolts\HtmlString;
use Mariner\View\Component;
use Mariner\View\ComponentSlot;
use Mariner\View\Factory;
use Mariner\View\View;
use Mockery;

beforeEach(function () {
    $this->config = Mockery::mock(Config::class);

    $container = new ControlPanel;

    $this->viewFactory = Mockery::mock(Factory::class);

    $container->registerInstance('view', $this->viewFactory);
    $container->alias('view', FactoryContract::class);
    $container->registerInstance('config', $this->config);

    ControlPanel::setInstance($container);
    Facade::setFacadeApplication($container);
});

afterEach(function () {
    Facade::clearResolvedInstances();
    Facade::setFacadeApplication(null);
    ControlPanel::setInstance(null);
    Component::flushCache();
    Component::forgetFactory();
    Component::resolveComponentsUsing(null);
});

test('inline views get created', function () {
    $this->config->expects('get')->with('view.compiled')->andReturn('/tmp');
    $this->viewFactory->expects('exists')->andReturn(false);
    $this->viewFactory->expects('addNamespace')->with('__components', '/tmp');

    $component = new TestInlineViewComponent;
    expect($component->resolveView())->toBe('__components::57b7a54afa0eb51fd9b88eec031c9e9e');
});

test('regular views get returned using view helper', function () {
    $view = Mockery::mock(View::class);
    $this->viewFactory->expects('make')->with('alert', [], [])->andReturn($view);

    $component = new TestRegularViewComponentUsingViewHelper;

    expect($component->resolveView())->toBe($view);
});

test('rendering string closure from component', function () {
    $this->config->expects('get')->with('view.compiled')->andReturn('/tmp');
    $this->viewFactory->expects('exists')->andReturn(false);
    $this->viewFactory->expects('addNamespace')->with('__components', '/tmp');

    $component = new class() extends Component
    {
        protected $title;

        public function __construct($title = 'World')
        {
            $this->title = $title;
        }

        public function render()
        {
            return function (array $data) {
                return "<p>Hello {$this->title}</p>";
            };
        }
    };

    $closure = $component->resolveView();

    $viewPath = $closure([]);

    expect($closure)->toBeInstanceOf(Closure::class);
    expect($viewPath)->toBe('__components::9cc08f5001b343c093ee1a396da820dc');

    $hash = str_replace('__components::', '', $viewPath);
    expect(file_get_contents("/tmp/{$hash}.blade.php"))->toBe('<p>Hello World</p>');
});

test('regular views get returned using view method', function () {
    $view = Mockery::mock(View::class);
    $this->viewFactory->expects('make')->with('alert', [], [])->andReturn($view);

    $component = new TestRegularViewComponentUsingViewMethod;

    expect($component->resolveView())->toBe($view);
});

test('regular view names get returned', function () {
    $this->viewFactory->expects('exists')->andReturn(true);
    $this->viewFactory->shouldReceive('addNamespace')->never();

    $component = new TestRegularViewNameViewComponent;

    expect($component->resolveView())->toBe('alert');
});

test('htmlable get returned', function () {
    $component = new TestHtmlableReturningViewComponent;

    $view = $component->resolveView();

    expect($view)->toBeInstanceOf(Htmlable::class);
    expect($view->toHtml())->toBe('<p>Hello foo</p>');
});

test('resolve with unresolvable dependency', function () {

    TestInlineViewComponentWhereRenderDependsOnProps::resolve([]);
})->throws(DataBindingException::class, 'Unresolvable dependency resolving');

test('resolve dependencies without container', function () {
    $component = TestInlineViewComponentWhereRenderDependsOnProps::resolve(['content' => 'foo']);
    expect($component->render())->toBe('foo');

    $component = new class extends Component
    {
        public $content;

        public function __construct($a = null, $b = null)
        {
            $this->content = $a.$b;
        }

        public function render()
        {
            return $this->content;
        }
    };

    $component = $component::resolve(['a' => 'a', 'b' => 'b']);
    $component = $component::resolve(['b' => 'b', 'a' => 'a']);
    expect($component->render())->toBe('ab');
});

test('resolve dependencies with container if necessary', function () {
    $component = TestInlineViewComponentWithContainerDependencies::resolve([]);
    expect($component->dependency)->toBe($this->viewFactory);

    $component = TestInlineViewComponentWithContainerDependenciesAndProps::resolve(['content' => 'foo']);
    expect($component->dependency)->toBe($this->viewFactory);
    expect($component->render())->toBe('foo');
});

test('resolve components using', function () {
    $component = new TestInlineViewComponent;

    Component::resolveComponentsUsing(function ($class, $data) use ($component) {
        expect($class)->toBe(Component::class);
        expect($data)->toBe(['foo' => 'bar']);

        return $component;
    });

    expect(Component::resolve(['foo' => 'bar']))->toBe($component);
});

test('blade view cache with regular view name view component', function () {
    $component = new TestRegularViewNameViewComponent;

    $this->viewFactory->expects('exists')->times(2)->andReturn(true);

    expect($component->resolveView())->toBe('alert');
    expect($component->resolveView())->toBe('alert');
    expect($component->resolveView())->toBe('alert');
    expect($component->resolveView())->toBe('alert');

    $cache = (fn () => $component::$bladeViewCache)->call($component);
    expect($cache)->toBe([$component::class.'::alert' => 'alert']);

    $component::flushCache();

    $cache = (fn () => $component::$bladeViewCache)->call($component);
    expect($cache)->toBe([]);

    expect($component->resolveView())->toBe('alert');
    expect($component->resolveView())->toBe('alert');
    expect($component->resolveView())->toBe('alert');
    expect($component->resolveView())->toBe('alert');
});

test('blade view cache with inline view component', function () {
    $component = new TestInlineViewComponent;

    $this->viewFactory->expects('exists')->times(2)->andReturn(false);

    $this->config->expects('get')->times(2)->with('view.compiled')->andReturn('/tmp');

    $this->viewFactory->expects('addNamespace')
        ->with('__components', '/tmp')
        ->times(2);

    $compiledViewName = '__components::57b7a54afa0eb51fd9b88eec031c9e9e';
    $contents = '::Hello {{ $title }}';
    $cacheKey = $component::class.$contents;

    expect($component->resolveView())->toBe($compiledViewName);
    expect($component->resolveView())->toBe($compiledViewName);
    expect($component->resolveView())->toBe($compiledViewName);
    expect($component->resolveView())->toBe($compiledViewName);

    $cache = (fn () => $component::$bladeViewCache)->call($component);
    expect($cache)->toBe([$cacheKey => $compiledViewName]);

    $component::flushCache();

    $cache = (fn () => $component::$bladeViewCache)->call($component);
    expect($cache)->toBe([]);

    expect($component->resolveView())->toBe($compiledViewName);
    expect($component->resolveView())->toBe($compiledViewName);
    expect($component->resolveView())->toBe($compiledViewName);
    expect($component->resolveView())->toBe($compiledViewName);
});

test('blade view cache with inline view component where render depends on props', function () {
    $componentA = new TestInlineViewComponentWhereRenderDependsOnProps('A');
    $componentB = new TestInlineViewComponentWhereRenderDependsOnProps('B');

    $this->viewFactory->expects('exists')->times(2)->andReturn(false);

    $this->config->expects('get')->times(2)->with('view.compiled')->andReturn('/tmp');

    $this->viewFactory->expects('addNamespace')
        ->with('__components', '/tmp')
        ->times(2);

    $compiledViewNameA = '__components::9b0498cbe3839becd0d496e05c553485';
    $compiledViewNameB = '__components::9d1b9bc4078a3e7274d3766ca02423f3';
    $cacheAKey = $componentA::class.'::A';
    $cacheBKey = $componentB::class.'::B';

    expect($componentA->resolveView())->toBe($compiledViewNameA);
    expect($componentA->resolveView())->toBe($compiledViewNameA);
    expect($componentB->resolveView())->toBe($compiledViewNameB);
    expect($componentB->resolveView())->toBe($compiledViewNameB);

    $cacheA = (fn () => $componentA::$bladeViewCache)->call($componentA);
    $cacheB = (fn () => $componentB::$bladeViewCache)->call($componentB);
    expect($cacheB)->toBe($cacheA);
    expect($cacheA)->toBe([
        $cacheAKey => $compiledViewNameA,
        $cacheBKey => $compiledViewNameB,
    ]);

    $componentA::flushCache();

    $cacheA = (fn () => $componentA::$bladeViewCache)->call($componentA);
    $cacheB = (fn () => $componentB::$bladeViewCache)->call($componentB);
    expect($cacheB)->toBe($cacheA);
    expect($cacheA)->toBe([]);
});

test('factory gets shared between components', function () {
    $regular = new TestRegularViewNameViewComponent;
    $inline = new TestInlineViewComponent;

    $getFactory = fn ($component) => (fn () => $component->factory())->call($component);

    expect($getFactory($regular))->toBe($this->viewFactory);

    ControlPanel::getInstance()->registerInstance('view', 'foo');
    expect($getFactory($inline))->toBe($this->viewFactory);

    Component::forgetFactory();
    expect($getFactory($inline))->not->toBe($this->viewFactory);
});

test('component slot is empty', function () {
    $slot = new ComponentSlot();

    expect((bool) $slot->isEmpty())->toBeTrue();
});

test('component slot sanitized empty', function () {
    // default sanitizer should remove all html tags
    $slot = new ComponentSlot('<!-- test -->');

    $linebreakingSlot = new ComponentSlot("\n  \t");

    $moreComplexSlot = new ComponentSlot('<!--
        <p>commented HTML</p>
        <img border="0" src="" alt="">
        -->');

    expect((bool) $slot->hasActualContent())->toBeFalse();
    expect((bool) $linebreakingSlot->hasActualContent('trim'))->toBeFalse();
    expect((bool) $moreComplexSlot->hasActualContent())->toBeFalse();
});

test('component slot sanitized not empty', function () {
    // default sanitizer should remove all html tags
    $slot = new ComponentSlot('<!-- test -->not empty');

    $linebreakingSlot = new ComponentSlot("\ntest  \t");

    $moreComplexSlot = new ComponentSlot('before<!--
        <p>commented HTML</p>
        <img border="0" src="" alt="">
        -->after');

    expect((bool) $slot->hasActualContent())->toBeTrue();
    expect((bool) $linebreakingSlot->hasActualContent('trim'))->toBeTrue();
    expect((bool) $moreComplexSlot->hasActualContent())->toBeTrue();
});

test('component slot is not empty', function () {
    $slot = new ComponentSlot('test');

    $anotherSlot = new ComponentSlot('test<!-- test -->');

    $moreComplexSlot = new ComponentSlot('t<!--
        <p>Look at this cool image:</p>
        <img border="0" src="pic_trulli.jpg" alt="Trulli">
        -->est');

    expect((bool) $slot->hasActualContent())->toBeTrue();
    expect((bool) $anotherSlot->hasActualContent())->toBeTrue();
    expect((bool) $moreComplexSlot->hasActualContent())->toBeTrue();
});

test('data only includes non static non ignored public properties', function () {
    $component = new TestComponentWithStaticAndIgnoredProperties;

    $data = $component->data();

    expect($data['visible'])->toBe('bar');
    expect($data)->not->toHaveKey('staticProp');
    expect($data)->not->toHaveKey('__hidden');
});

class TestComponentWithStaticAndIgnoredProperties extends Component
{
    public static $staticProp = 'static';

    public $__hidden = 'hidden';

    public $visible = 'bar';

    public function render()
    {
        return 'Hello';
    }
}

class TestInlineViewComponent extends Component
{
    public $title;

    public function __construct($title = 'foo')
    {
        $this->title = $title;
    }

    public function render()
    {
        return 'Hello {{ $title }}';
    }
}

class TestInlineViewComponentWithContainerDependencies extends Component
{
    public $dependency;

    public function __construct(FactoryContract $dependency)
    {
        $this->dependency = $dependency;
    }

    public function render()
    {
        return '';
    }
}

class TestInlineViewComponentWithContainerDependenciesAndProps extends Component
{
    public $content;

    public $dependency;

    public function __construct(FactoryContract $dependency, $content)
    {
        $this->content = $content;
        $this->dependency = $dependency;
    }

    public function render()
    {
        return $this->content;
    }
}

class TestInlineViewComponentWhereRenderDependsOnProps extends Component
{
    public $content;

    public function __construct($content)
    {
        $this->content = $content;
    }

    public function render()
    {
        return $this->content;
    }
}

class TestRegularViewComponentUsingViewHelper extends Component
{
    public $title;

    public function __construct($title = 'foo')
    {
        $this->title = $title;
    }

    public function render()
    {
        return view('alert');
    }
}

class TestRegularViewComponentUsingViewMethod extends Component
{
    public $title;

    public function __construct($title = 'foo')
    {
        $this->title = $title;
    }

    public function render()
    {
        return $this->view('alert');
    }
}

class TestRegularViewNameViewComponent extends Component
{
    public $title;

    public function __construct($title = 'foo')
    {
        $this->title = $title;
    }

    public function render()
    {
        return 'alert';
    }
}

class TestHtmlableReturningViewComponent extends Component
{
    protected $title;

    public function __construct($title = 'foo')
    {
        $this->title = $title;
    }

    public function render()
    {
        return new HtmlString("<p>Hello {$this->title}</p>");
    }
}
