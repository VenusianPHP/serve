<?php

namespace Venusian\Serve\Tests\View;

use Closure;
use ErrorException;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Signals\SignalDispatcher as DispatcherContract;
use Mariner\Contracts\Translation\Translator;
use Mariner\Contracts\View\Engine;
use Mariner\Contracts\View\View as ViewContract;
use Voyager\Signals\SignalDispatcher;
use Voyager\Filesystem\Filesystem;
use Voyager\NutsAndBolts\HtmlString;
use Voyager\NutsAndBolts\LazyCollection;
use Mariner\View\Compilers\CompilerInterface;
use Mariner\View\Engines\CompilerEngine;
use Mariner\View\Engines\EngineResolver;
use Mariner\View\Engines\PhpEngine;
use Mariner\View\Factory;
use Mariner\View\View;
use Mariner\View\ViewFinderInterface;
use InvalidArgumentException;
use Mockery;
use ReflectionFunction;

test('make creates new view instance with proper path and engine', function () {
    unset($_SERVER['__test.view']);

    $factory = getFactory();
    $factory->getFinder()->expects('find')->with('view')->andReturn('path.php');
    $engine = Mockery::mock(Engine::class);
    $factory->getEngineResolver()->expects('resolve')->with('php')->andReturn($engine);
    $factory->getFinder()->expects('addExtension')->with('php');
    $factory->setDispatcher(new SignalDispatcher);
    $factory->creator('view', function ($view) {
        $_SERVER['__test.view'] = $view;
    });
    $factory->addExtension('php', 'php');
    $view = $factory->make('view', ['foo' => 'bar'], ['baz' => 'boom']);

    expect($view->getEngine())->toBe($engine);
    expect($view)->toBe($_SERVER['__test.view']);

    unset($_SERVER['__test.view']);
});

test('exists passes and fails views', function () {
    $factory = getFactory();
    $factory->getFinder()->expects('find')->with('foo')->andThrow(InvalidArgumentException::class);
    $factory->getFinder()->expects('find')->with('bar')->andReturn('path.php');

    expect($factory->exists('foo'))->toBeFalse();
    expect($factory->exists('bar'))->toBeTrue();
});

test('rendering once checks', function () {
    $factory = getFactory();
    expect($factory->hasRenderedOnce('foo'))->toBeFalse();
    $factory->markAsRenderedOnce('foo');
    expect($factory->hasRenderedOnce('foo'))->toBeTrue();
    $factory->flushState();
    expect($factory->hasRenderedOnce('foo'))->toBeFalse();
});

test('first creates new view instance with proper path', function () {
    unset($_SERVER['__test.view']);

    $factory = getFactory();
    $factory->getFinder()->expects('find')->times(2)->with('view')->andReturn('path.php');
    $factory->getFinder()->expects('find')->with('bar')->andThrow(InvalidArgumentException::class);
    $engine = Mockery::mock(Engine::class);
    $factory->getEngineResolver()->expects('resolve')->with('php')->andReturn($engine);
    $factory->getFinder()->expects('addExtension')->with('php');
    $factory->setDispatcher(new SignalDispatcher);
    $factory->creator('view', function ($view) {
        $_SERVER['__test.view'] = $view;
    });
    $factory->addExtension('php', 'php');
    $view = $factory->first(['bar', 'view'], ['foo' => 'bar'], ['baz' => 'boom']);

    expect($view)->toBeInstanceOf(ViewContract::class);
    expect($view->getEngine())->toBe($engine);
    expect($view)->toBe($_SERVER['__test.view']);

    unset($_SERVER['__test.view']);
});

test('first throws invalid argument exception if none found', function () {

    $factory = getFactory();
    $factory->getFinder()->expects('find')->with('view')->andThrow(InvalidArgumentException::class);
    $factory->getFinder()->expects('find')->with('bar')->andThrow(InvalidArgumentException::class);
    $factory->getFinder()->expects('addExtension')->with('php');
    $factory->addExtension('php', 'php');
    $factory->first(['bar', 'view'], ['foo' => 'bar'], ['baz' => 'boom']);
})->throws(InvalidArgumentException::class);

test('render each creates view for each item in array', function () {
    $factory = Mockery::mock(Factory::class.'[make]', getFactoryArgs());
    $mockView1 = Mockery::mock(ViewContract::class);
    $factory->expects('make')->with('foo', ['key' => 'bar', 'value' => 'baz'])->andReturn($mockView1);
    $mockView2 = Mockery::mock(ViewContract::class);
    $factory->expects('make')->with('foo', ['key' => 'breeze', 'value' => 'boom'])->andReturn($mockView2);
    $mockView1->expects('render')->andReturn('dayle');
    $mockView2->expects('render')->andReturn('rees');

    $result = $factory->renderEach('foo', ['bar' => 'baz', 'breeze' => 'boom'], 'value');

    expect($result)->toBe('daylerees');
});

test('empty views can be returned from render each', function () {
    $factory = Mockery::mock(Factory::class.'[make]', getFactoryArgs());
    $mockView = Mockery::mock(ViewContract::class);
    $factory->expects('make')->with('foo')->andReturn($mockView);
    $mockView->expects('render')->andReturn('empty');

    expect($factory->renderEach('view', [], 'iterator', 'foo'))->toBe('empty');
});

test('raw strings may be returned from render each', function () {
    expect(getFactory()->renderEach('foo', [], 'item', 'raw|foo'))->toBe('foo');
});

test('environment adds extension with custom resolver', function () {
    $factory = getFactory();

    $resolver = function () {
        //
    };

    $factory->getFinder()->expects('addExtension')->with('foo');
    $factory->getEngineResolver()->expects('register')->with('bar', $resolver);
    $factory->getFinder()->expects('find')->with('view')->andReturn('path.foo');
    $engine = Mockery::mock(Engine::class);
    $factory->getEngineResolver()->expects('resolve')->with('bar')->andReturn($engine);
    $factory->getDispatcher()->expects('hasListeners')->andReturn(false);

    $factory->addExtension('foo', 'bar', $resolver);

    $view = $factory->make('view', ['data']);
    expect($view->getEngine())->toBe($engine);
});

test('adding extension prepends not appends', function () {
    $factory = getFactory();
    $factory->getFinder()->expects('addExtension')->with('foo');

    $factory->addExtension('foo', 'bar');

    $extensions = $factory->getExtensions();
    expect(reset($extensions))->toBe('bar');
    expect(key($extensions))->toBe('foo');
});

test('prepended extension overrides existing extensions', function () {
    $factory = getFactory();
    $factory->getFinder()->expects('addExtension')->with('foo');
    $factory->getFinder()->expects('addExtension')->with('baz');

    $factory->addExtension('foo', 'bar');
    $factory->addExtension('baz', 'bar');

    $extensions = $factory->getExtensions();
    expect(reset($extensions))->toBe('bar');
    expect(key($extensions))->toBe('baz');
});

test('call creators does dispatch events when is necessary', function () {
    $factory = getFactory();

    $factory->getDispatcher()
        ->expects('listen')
        ->with('creating: name', Mockery::type(Closure::class));

    $factory->getDispatcher()->expects('hasListeners')->andReturn(true);

    $factory->getDispatcher()
        ->expects('dispatch')
        ->with('creating: name', Mockery::type('array'));

    $view = Mockery::mock(View::class);
    $view->expects('name')->andReturn('name');

    $factory->creator('name', fn () => true);

    $factory->callCreator($view);
});

test('call creators does dispatch events when is necessary using namespaced wildcards', function () {
    $factory = getFactory();

    $factory->getDispatcher()
        ->expects('listen')
        ->with('creating: namespaced::*', Mockery::type(Closure::class));

    $factory->getDispatcher()->expects('hasListeners')->andReturn(true);

    $factory->getDispatcher()
        ->expects('dispatch')
        ->with('creating: namespaced::my-package-view', Mockery::type('array'));

    $view = Mockery::mock(View::class);
    $view->expects('name')->andReturn('namespaced::my-package-view');

    $factory->creator('namespaced::*', fn () => true);

    $factory->callCreator($view);
});

test('call creators does dispatch events when is necessary using namespaced nested wildcards', function () {
    $factory = getFactory();

    $factory->getDispatcher()
        ->expects('listen')
        ->with('creating: namespaced::*', Mockery::type(Closure::class));

    $factory->getDispatcher()
        ->expects('listen')
        ->with('creating: welcome', Mockery::type(Closure::class));

    $factory->getDispatcher()->expects('hasListeners')->andReturn(true);

    $factory->getDispatcher()
        ->expects('dispatch')
        ->with('creating: namespaced::my-package-view', Mockery::type('array'));

    $view = Mockery::mock(View::class);
    $view->expects('name')->andReturn('namespaced::my-package-view');

    $factory->creator(['namespaced::*', 'welcome'], fn () => true);

    $factory->callCreator($view);
});

test('call creators does dispatch events when is necessary using wildcards', function () {
    $factory = getFactory();

    $factory->getDispatcher()
        ->expects('listen')
        ->with('creating: *', Mockery::type(Closure::class));

    $factory->getDispatcher()->expects('hasListeners')->andReturn(true);

    $factory->getDispatcher()
        ->expects('dispatch')
        ->with('creating: name', Mockery::type('array'));

    $view = Mockery::mock(View::class);
    $view->expects('name')->andReturn('name');

    $factory->creator('*', fn () => true);

    $factory->callCreator($view);
});

test('call creators does dispatch events when is necessary using normalized names', function () {
    $factory = getFactory();

    $factory->getDispatcher()
        ->expects('listen')
        ->with('creating: components.button', Mockery::type(Closure::class));

    $factory->getDispatcher()->expects('hasListeners')->andReturn(true);

    $factory->getDispatcher()
        ->expects('dispatch')
        ->with('creating: components/button', Mockery::type('array'));

    $view = Mockery::mock(View::class);
    $view->expects('name')
        ->andReturn('components/button');

    $factory->creator('components.button', fn () => true);

    $factory->callCreator($view);
});

test('call composer does dispatch events when is necessary', function () {
    $factory = getFactory();

    $factory->getDispatcher()
        ->expects('listen')
        ->with('composing: name', Mockery::type(Closure::class));

    $factory->getDispatcher()->expects('hasListeners')->andReturn(true);

    $factory->getDispatcher()
        ->expects('dispatch')
        ->with('composing: name', Mockery::type('array'));

    $view = Mockery::mock(View::class);
    $view->expects('name')->andReturn('name');

    $factory->composer('name', fn () => true);

    $factory->callComposer($view);
});

test('call composer does dispatch events when is necessary and using the array format', function () {
    $factory = getFactory();

    $factory->getDispatcher()
        ->expects('listen')
        ->with('composing: name', Mockery::type(Closure::class));

    $factory->getDispatcher()->expects('hasListeners')->andReturn(true);

    $factory->getDispatcher()
        ->expects('dispatch')
        ->with('composing: name', Mockery::type('array'));

    $view = Mockery::mock(View::class);
    $view->expects('name')->andReturn('name');

    $factory->composer(['name'], fn () => true);

    $factory->callComposer($view);
});

test('call composers does dispatch events when is necessary using namespaced wildcards', function () {
    $factory = getFactory();

    $factory->getDispatcher()
        ->expects('listen')
        ->with('composing: namespaced::*', Mockery::type(Closure::class));

    $factory->getDispatcher()->expects('hasListeners')->andReturn(true);

    $factory->getDispatcher()
        ->expects('dispatch')
        ->with('composing: namespaced::my-package-view', Mockery::type('array'));

    $view = Mockery::mock(View::class);
    $view->expects('name')->andReturn('namespaced::my-package-view');

    $factory->composer('namespaced::*', fn () => true);

    $factory->callComposer($view);
});

test('call composers does dispatch events when is necessary using namespaced nested wildcards', function () {
    $factory = getFactory();
    $factory->getDispatcher()
        ->expects('listen')
        ->with('composing: namespaced::*', Mockery::type(Closure::class));

    $factory->getDispatcher()
        ->expects('listen')
        ->with('composing: welcome', Mockery::type(Closure::class));

    $factory->getDispatcher()->expects('hasListeners')->andReturn(true);

    $factory->getDispatcher()
        ->expects('dispatch')
        ->with('composing: namespaced::my-package-view', Mockery::type('array'));

    $view = Mockery::mock(View::class);
    $view->expects('name')->andReturn('namespaced::my-package-view');

    $factory->composer(['namespaced::*', 'welcome'], fn () => true);

    $factory->callComposer($view);
});

test('call composers does dispatch events when is necessary using wildcards', function () {
    $factory = getFactory();

    $factory->getDispatcher()->expects('hasListeners')->andReturn(true);

    $factory->getDispatcher()
        ->expects('listen')
        ->with('composing: *', Mockery::type(Closure::class));

    $factory->getDispatcher()
        ->expects('dispatch')
        ->with('composing: name', Mockery::type('array'));

    $view = Mockery::mock(View::class);
    $view->expects('name')->andReturn('name');

    $factory->composer('*', fn () => true);

    $factory->callComposer($view);
});

test('call composers does dispatch events when is necessary using normalized names', function () {
    $factory = getFactory();

    $factory->getDispatcher()->expects('hasListeners')->andReturn(true);

    $factory->getDispatcher()
        ->expects('listen')
        ->with('composing: components.button', Mockery::type(Closure::class));

    $factory->getDispatcher()
        ->expects('dispatch')
        ->with('composing: components/button', Mockery::type('array'));

    $view = Mockery::mock(View::class);
    $view->expects('name')->andReturn('components/button');

    $factory->composer('components.button', fn () => true);

    $factory->callComposer($view);
});

test('composers are properly registered', function () {
    $factory = getFactory();
    $factory->getDispatcher()->expects('listen')->with('composing: foo', Mockery::type(Closure::class));
    $callback = $factory->composer('foo', function () {
        return 'bar';
    });
    $callback = $callback[0];

    expect($callback())->toBe('bar');
});

test('composers can be mass registered', function () {
    $factory = getFactory();
    $factory->getDispatcher()->expects('listen')->with('composing: bar', Mockery::type(Closure::class));
    $factory->getDispatcher()->expects('listen')->with('composing: qux', Mockery::type(Closure::class));
    $factory->getDispatcher()->expects('listen')->with('composing: foo', Mockery::type(Closure::class));
    $composers = $factory->composers([
        'foo' => 'bar',
        'baz@baz' => ['qux', 'foo'],
    ]);

    expect($composers)->toHaveCount(3);
    $reflections = [
        new ReflectionFunction($composers[0]),
        new ReflectionFunction($composers[1]),
    ];
    expect($reflections[0]->getStaticVariables())->toEqual(['class' => 'foo', 'method' => 'compose']);
    expect($reflections[1]->getStaticVariables())->toEqual(['class' => 'baz', 'method' => 'baz']);
});

test('class callbacks', function () {
    $factory = getFactory();
    $factory->getDispatcher()->expects('listen')->with('composing: foo', Mockery::type(Closure::class));
    $factory->setContainer(new ControlPanel);
    $callback = $factory->composer('foo', ViewComposerStub::class);
    $callback = $callback[0];

    expect($callback('view'))->toBe('view composed');
});

test('class callbacks with methods', function () {
    $factory = getFactory();
    $factory->getDispatcher()->expects('listen')->with('composing: foo', Mockery::type(Closure::class));
    $factory->setContainer(new ControlPanel);
    $callback = $factory->composer('foo', ViewComposerStub::class.'@doComposer');
    $callback = $callback[0];

    expect($callback('view'))->toBe('view do-composed');
});

test('call composer calls proper event', function () {
    $factory = getFactory();
    $view = Mockery::mock(View::class);
    $dispatcher = Mockery::mock(DispatcherContract::class);
    $factory->setDispatcher($dispatcher);

    $dispatcher->shouldReceive('listen', Mockery::any())->once();

    $view->expects('name')->andReturn('name');

    $factory->composer('name', fn () => true);

    $factory->getDispatcher()->expects('hasListeners')->andReturn(true);
    $factory->getDispatcher()->expects('dispatch')->with('composing: name', [$view]);

    $factory->callComposer($view);
});

test('composers are registered with slash and dot', function () {
    $factory = getFactory();
    $factory->getDispatcher()->expects('listen')->with('composing: foo.bar', Mockery::any())->times(2);
    $factory->composer('foo.bar', '');
    $factory->composer('foo/bar', '');
});

test('render count handling', function () {
    $factory = getFactory();
    $factory->incrementRender();
    expect($factory->doneRendering())->toBeFalse();
    $factory->decrementRender();
    expect($factory->doneRendering())->toBeTrue();
});

test('yield default', function () {
    $factory = getFactory();
    expect($factory->yieldContent('foo', 'hi'))->toBe('hi');
});

test('yield default is escaped', function () {
    $factory = getFactory();
    expect($factory->yieldContent('foo', '<p>hi</p>'))->toBe('&lt;p&gt;hi&lt;/p&gt;');
});

test('yield default view is not escaped twice', function () {
    $factory = getFactory();
    $view = Mockery::mock(View::class);
    $view->expects('__toString')->andReturn('<p>hi</p>&lt;p&gt;already escaped&lt;/p&gt;');
    expect($factory->yieldContent('foo', $view))->toBe('<p>hi</p>&lt;p&gt;already escaped&lt;/p&gt;');
});

test('basic fragment handling', function () {
    $factory = getFactory();
    $factory->startFragment('foo');
    echo 'hi';
    expect($factory->stopFragment())->toBe('hi');
});

test('basic section handling', function () {
    $factory = getFactory();
    $factory->startSection('foo');
    echo 'hi';
    $factory->stopSection();
    expect($factory->yieldContent('foo'))->toBe('hi');
});

test('basic section default', function () {
    $factory = getFactory();
    $factory->startSection('foo', 'hi');
    expect($factory->yieldContent('foo'))->toBe('hi');
});

test('basic section default is escaped', function () {
    $factory = getFactory();
    $factory->startSection('foo', '<p>hi</p>');
    expect($factory->yieldContent('foo'))->toBe('&lt;p&gt;hi&lt;/p&gt;');
});

test('basic section default view is not escaped twice', function () {
    $factory = getFactory();
    $view = Mockery::mock(View::class);
    $view->expects('__toString')->andReturn('<p>hi</p>&lt;p&gt;already escaped&lt;/p&gt;');
    $factory->startSection('foo', $view);
    expect($factory->yieldContent('foo'))->toBe('<p>hi</p>&lt;p&gt;already escaped&lt;/p&gt;');
});

test('section extending', function () {
    $placeholder = Factory::parentPlaceholder('foo');
    $factory = getFactory();
    $factory->startSection('foo');
    echo 'hi '.$placeholder;
    $factory->stopSection();
    $factory->startSection('foo');
    echo 'there';
    $factory->stopSection();
    expect($factory->yieldContent('foo'))->toBe('hi there');
});

test('section multiple extending', function () {
    $placeholder = Factory::parentPlaceholder('foo');
    $factory = getFactory();
    $factory->startSection('foo');
    echo 'hello '.$placeholder.' nice to see you '.$placeholder;
    $factory->stopSection();
    $factory->startSection('foo');
    echo 'my '.$placeholder;
    $factory->stopSection();
    $factory->startSection('foo');
    echo 'friend';
    $factory->stopSection();
    expect($factory->yieldContent('foo'))->toBe('hello my friend nice to see you my friend');
});

test('component handling', function () {
    $factory = getFactory();
    $factory->getFinder()->expects('find')->andReturn(__DIR__.'/Fixtures/component.php');
    $factory->getEngineResolver()->expects('resolve')->andReturn(new PhpEngine(new Filesystem));
    $factory->getDispatcher()->expects('hasListeners')->times(2)->andReturn(false);
    $factory->startComponent('component', ['name' => 'Taylor']);
    $factory->slot('title');
    $factory->slot('website', 'laravel.com', []);
    echo 'title<hr>';
    $factory->endSlot();
    echo 'component';
    $contents = $factory->renderComponent();
    expect($contents)->toBe('title<hr> component Taylor laravel.com');
});

test('component handling using view object', function () {
    $factory = getFactory();
    $factory->getFinder()->expects('find')->andReturn(__DIR__.'/Fixtures/component.php');
    $factory->getEngineResolver()->expects('resolve')->andReturn(new PhpEngine(new Filesystem));
    $factory->getDispatcher()->expects('hasListeners')->times(2)->andReturn(false);
    $factory->startComponent($factory->make('component'), ['name' => 'Taylor']);
    $factory->slot('title');
    $factory->slot('website', 'laravel.com', []);
    echo 'title<hr>';
    $factory->endSlot();
    echo 'component';
    $contents = $factory->renderComponent();
    expect($contents)->toBe('title<hr> component Taylor laravel.com');
});

test('component handling using closure', function () {
    $factory = getFactory();
    $factory->getFinder()->expects('find')->andReturn(__DIR__.'/Fixtures/component.php');
    $factory->getEngineResolver()->expects('resolve')->andReturn(new PhpEngine(new Filesystem));
    $factory->getDispatcher()->expects('hasListeners')->times(2)->andReturn(false);
    $factory->startComponent(function ($data) use ($factory) {
        expect($data)->toHaveKey('name');
        expect($data['name'])->toBe('Taylor');

        return $factory->make('component');
    }, ['name' => 'Taylor']);
    $factory->slot('title');
    $factory->slot('website', 'laravel.com', []);
    echo 'title<hr>';
    $factory->endSlot();
    echo 'component';
    $contents = $factory->renderComponent();
    expect($contents)->toBe('title<hr> component Taylor laravel.com');
});

test('component handling using htmlable', function () {
    $factory = getFactory();
    $factory->startComponent(new HtmlString('laravel.com'));
    $contents = $factory->renderComponent();
    expect($contents)->toBe('laravel.com');
});

test('flush state resets slots', function () {
    $factory = getFactory();

    $factory->slot('title');
    echo 'laravel.com';
    $factory->endSlot();

    $factory->flushState();

    expect((fn () => $this->renderState()->slots)->call($factory))->toBe([]);
    expect((fn () => $this->renderState()->slotStack)->call($factory))->toBe([]);
});

test('translation', function () {
    $container = new ControlPanel;
    $translator = Mockery::mock(Translator::class);
    $translator->expects('get')->with('Foo', ['name' => 'taylor'])->andReturn('Bar');
    $container->registerInstance('translator', $translator);
    $factory = getFactory();
    $factory->setContainer($container);
    $factory->startTranslation(['name' => 'taylor']);
    echo 'Foo';
    $string = $factory->renderTranslation();

    expect($string)->toBe('Bar');
});

test('single stack push', function () {
    $factory = getFactory();
    $factory->startPush('foo');
    echo 'hi';
    $factory->stopPush();
    expect($factory->yieldPushContent('foo'))->toBe('hi');
});

test('multiple stack push', function () {
    $factory = getFactory();
    $factory->startPush('foo');
    echo 'hi';
    $factory->stopPush();
    $factory->startPush('foo');
    echo ', Hello!';
    $factory->stopPush();
    expect($factory->yieldPushContent('foo'))->toBe('hi, Hello!');
});

test('single stack prepend', function () {
    $factory = getFactory();
    $factory->startPrepend('foo');
    echo 'hi';
    $factory->stopPrepend();
    expect($factory->yieldPushContent('foo'))->toBe('hi');
});

test('multiple stack prepend', function () {
    $factory = getFactory();
    $factory->startPrepend('foo');
    echo ', Hello!';
    $factory->stopPrepend();
    $factory->startPrepend('foo');
    echo 'hi';
    $factory->stopPrepend();
    expect($factory->yieldPushContent('foo'))->toBe('hi, Hello!');
});

test('session appending', function () {
    $factory = getFactory();
    $factory->startSection('foo');
    echo 'hi';
    $factory->appendSection();
    $factory->startSection('foo');
    echo 'there';
    $factory->appendSection();
    expect($factory->yieldContent('foo'))->toBe('hithere');
});

test('yield section stops and yields', function () {
    $factory = getFactory();
    $factory->startSection('foo');
    echo 'hi';
    expect($factory->yieldSection())->toBe('hi');
});

test('inject starts section with content', function () {
    $factory = getFactory();
    $factory->inject('foo', 'hi');
    expect($factory->yieldContent('foo'))->toBe('hi');
});

test('empty string is returned for non sections', function () {
    $factory = getFactory();
    expect($factory->yieldContent('foo'))->toBeEmpty();
});

test('section flushing', function () {
    $factory = getFactory();
    $factory->startSection('foo');
    echo 'hi';
    $factory->stopSection();

    expect($factory->getSections())->toHaveCount(1);

    $factory->flushSections();

    expect($factory->getSections())->toHaveCount(0);
});

test('has section', function () {
    $factory = getFactory();
    $factory->startSection('foo');
    echo 'hi';
    $factory->stopSection();

    expect($factory->hasSection('foo'))->toBeTrue();
    expect($factory->hasSection('bar'))->toBeFalse();
});

test('section missing', function () {
    $factory = getFactory();
    $factory->startSection('foo');
    echo 'hello world';
    $factory->stopSection();

    expect($factory->sectionMissing('bar'))->toBeTrue();
    expect($factory->sectionMissing('foo'))->toBeFalse();
});

test('get section', function () {
    $factory = getFactory();
    $factory->startSection('foo');
    echo 'hi';
    $factory->stopSection();

    expect($factory->getSection('foo'))->toBe('hi');
    expect($factory->getSection('bar'))->toBeNull();
    expect($factory->getSection('bar', 'default'))->toBe('default');
});

test('make with slash and dot', function () {
    $factory = getFactory();
    $factory->getFinder()->expects('find')->times(2)->with('foo.bar')->andReturn('path.php');
    $factory->getEngineResolver()->expects('resolve')->times(2)->with('php')->andReturn(Mockery::mock(Engine::class));
    $factory->getDispatcher()->expects('hasListeners')->times(2)->andReturn(false);
    $factory->make('foo/bar');
    $factory->make('foo.bar');
});

test('namespaced view names are normalized properly', function () {
    $factory = getFactory();
    $factory->getFinder()->expects('find')->times(2)->with('vendor/package::foo.bar')->andReturn('path.php');
    $factory->getEngineResolver()->expects('resolve')->times(2)->with('php')->andReturn(Mockery::mock(Engine::class));
    $factory->getDispatcher()->expects('hasListeners')->times(2)->andReturn(false);
    $factory->make('vendor/package::foo/bar');
    $factory->make('vendor/package::foo.bar');
});

test('exception is thrown for unknown extension', function () {

    $factory = getFactory();
    $factory->getFinder()->expects('find')->with('view')->andReturn('view.foo');
    $factory->make('view');
})->throws(InvalidArgumentException::class);

test('exceptions in sections are thrown', function () {

    $engine = new CompilerEngine(Mockery::mock(CompilerInterface::class), new Filesystem);
    $engine->getCompiler()->expects('getCompiledPath')->times(2)->andReturnUsing(function ($path) {
        return $path;
    });
    $engine->getCompiler()->expects('isExpired')->times(2)->andReturn(false);
    $factory = getFactory();
    $factory->getEngineResolver()->expects('resolve')->times(2)->andReturn($engine);
    $factory->getFinder()->expects('find')->with('layout')->andReturn(__DIR__.'/Fixtures/section-exception-layout.php');
    $factory->getFinder()->expects('find')->with('view')->andReturn(__DIR__.'/Fixtures/section-exception.php');
    $factory->getDispatcher()->expects('hasListeners')->times(4); // 2 "creating" + 2 "composing"...

    $factory->make('view')->render();
})->throws(ErrorException::class, 'section exception message');

test('extra stop section call throws exception', function () {

    $factory = getFactory();
    $factory->startSection('foo');
    $factory->stopSection();

    $factory->stopSection();
})->throws(InvalidArgumentException::class, 'Cannot end a section without first starting one.');

test('extra append section call throws exception', function () {

    $factory = getFactory();
    $factory->startSection('foo');
    $factory->stopSection();

    $factory->appendSection();
})->throws(InvalidArgumentException::class, 'Cannot end a section without first starting one.');

test('adding loops', function () {
    $factory = getFactory();

    $factory->addLoop([1, 2, 3]);

    $expectedLoop = [
        'iteration' => 0,
        'index' => 0,
        'remaining' => 3,
        'count' => 3,
        'first' => true,
        'last' => false,
        'odd' => false,
        'even' => true,
        'depth' => 1,
        'parent' => null,
    ];

    expect($factory->getLoopStack())->toEqual([$expectedLoop]);

    $factory->addLoop([1, 2, 3, 4]);

    $secondExpectedLoop = [
        'iteration' => 0,
        'index' => 0,
        'remaining' => 4,
        'count' => 4,
        'first' => true,
        'last' => false,
        'odd' => false,
        'even' => true,
        'depth' => 2,
        'parent' => (object) $expectedLoop,
    ];
    expect($factory->getLoopStack())->toEqual([$expectedLoop, $secondExpectedLoop]);

    $factory->popLoop();

    expect($factory->getLoopStack())->toEqual([$expectedLoop]);
});

test('adding loop does not close generator', function () {
    $factory = getFactory();

    $data = (new class
    {
        public function generate()
        {
            for ($count = 0; $count < 3; $count++) {
                yield ['a', 'b'];
            }
        }
    })->generate();

    $factory->addLoop($data);

    foreach ($data as $chunk) {
        expect($chunk)->toEqual(['a', 'b']);
    }
});

test('adding uncountable loop', function () {
    $factory = getFactory();

    $factory->addLoop('');

    $expectedLoop = [
        'iteration' => 0,
        'index' => 0,
        'remaining' => null,
        'count' => null,
        'first' => true,
        'last' => null,
        'odd' => false,
        'even' => true,
        'depth' => 1,
        'parent' => null,
    ];

    expect($factory->getLoopStack())->toEqual([$expectedLoop]);
});

test('adding lazy collection', function () {
    $factory = getFactory();

    $factory->addLoop(new LazyCollection(function () {
        $this->fail('LazyCollection\'s generator should not have been called');
    }));

    $expectedLoop = [
        'iteration' => 0,
        'index' => 0,
        'remaining' => null,
        'count' => null,
        'first' => true,
        'last' => null,
        'odd' => false,
        'even' => true,
        'depth' => 1,
        'parent' => null,
    ];

    expect($factory->getLoopStack())->toEqual([$expectedLoop]);
});

test('incrementing loop indices', function () {
    $factory = getFactory();

    $factory->addLoop([1, 2, 3, 4]);

    $factory->incrementLoopIndices();

    expect($factory->getLoopStack()[0]['iteration'])->toEqual(1);
    expect($factory->getLoopStack()[0]['index'])->toEqual(0);
    expect($factory->getLoopStack()[0]['remaining'])->toEqual(3);
    expect($factory->getLoopStack()[0]['odd'])->toBeTrue();
    expect($factory->getLoopStack()[0]['even'])->toBeFalse();

    $factory->incrementLoopIndices();

    expect($factory->getLoopStack()[0]['iteration'])->toEqual(2);
    expect($factory->getLoopStack()[0]['index'])->toEqual(1);
    expect($factory->getLoopStack()[0]['remaining'])->toEqual(2);
    expect($factory->getLoopStack()[0]['odd'])->toBeFalse();
    expect($factory->getLoopStack()[0]['even'])->toBeTrue();
});

test('reaching end of loop', function () {
    $factory = getFactory();

    $factory->addLoop([1, 2]);

    $factory->incrementLoopIndices();

    $factory->incrementLoopIndices();

    expect($factory->getLoopStack()[0]['last'])->toBeTrue();
});

test('incrementing loop indices of uncountable', function () {
    $factory = getFactory();

    $factory->addLoop('');

    $factory->incrementLoopIndices();

    $factory->incrementLoopIndices();

    expect($factory->getLoopStack()[0]['iteration'])->toEqual(2);
    expect($factory->getLoopStack()[0]['index'])->toEqual(1);
    expect($factory->getLoopStack()[0]['first'])->toBeFalse();
    expect($factory->getLoopStack()[0]['remaining'])->toBeNull();
    expect($factory->getLoopStack()[0]['last'])->toBeNull();
});

test('macro', function () {
    $factory = getFactory();
    $factory->macro('getFoo', function () {
        return 'Hello World';
    });
    expect($factory->getFoo())->toBe('Hello World');
});

function getFactory()
{
    return new Factory(
        Mockery::mock(EngineResolver::class),
        Mockery::mock(ViewFinderInterface::class),
        Mockery::mock(DispatcherContract::class)
    );
}

function getFactoryArgs()
{
    return [
        Mockery::mock(EngineResolver::class),
        Mockery::mock(ViewFinderInterface::class),
        Mockery::mock(DispatcherContract::class),
    ];
}

class ViewComposerStub
{
    public function compose($view)
    {
        return $view.' composed';
    }

    public function doComposer($view)
    {
        return $view.' do-composed';
    }
}
