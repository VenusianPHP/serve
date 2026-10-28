<?php

namespace Venusian\Serve\Tests\View;

use ArrayAccess;
use BadMethodCallException;
use Closure;
use Voyager\Contracts\NutsAndBolts\Arrayable;
use Mariner\Contracts\NutsAndBolts\Renderable;
use Mariner\Contracts\View\Engine;
use Mariner\NutsAndBolts\MessageBag;
use Mariner\NutsAndBolts\ViewErrorBag;
use Mariner\View\Factory;
use Mariner\View\View;
use Mockery;

test('data can be set on view', function () {
    $view = getView();
    $view->with('foo', 'bar');
    $view->with(['baz' => 'boom']);
    expect($view->getData())->toEqual(['foo' => 'bar', 'baz' => 'boom']);

    $view = getView();
    $view->withFoo('bar')->withBaz('boom');
    expect($view->getData())->toEqual(['foo' => 'bar', 'baz' => 'boom']);
});

test('render properly renders view', function () {
    $view = getView(['foo' => 'bar']);
    $view->getFactory()->expects('incrementRender')->ordered();
    $view->getFactory()->expects('callComposer')->ordered()->with($view);
    $view->getFactory()->expects('getShared')->andReturn(['shared' => 'foo']);
    $view->getEngine()->expects('get')->with('path', ['foo' => 'bar', 'shared' => 'foo'])->andReturn('contents');
    $view->getFactory()->expects('decrementRender')->ordered();
    $view->getFactory()->expects('flushStateIfDoneRendering');

    $callback = function (View $rendered, $contents) use ($view) {
        expect($rendered)->toEqual($view);
        expect($contents)->toBe('contents');
    };

    expect($view->render($callback))->toBe('contents');
});

test('render handling callback return values', function () {
    $view = getView();
    $view->getFactory()->expects('incrementRender')->times(3);
    $view->getFactory()->expects('callComposer')->times(3);
    $view->getFactory()->expects('getShared')->times(3)->andReturn(['shared' => 'foo']);
    $view->getEngine()->expects('get')->times(3)->andReturn('contents');
    $view->getFactory()->expects('decrementRender')->times(3);
    $view->getFactory()->expects('flushStateIfDoneRendering')->times(3);

    expect($view->render(function () {
        return 'new contents';
    }))->toBe('new contents');

    expect($view->render(function () {
        return '';
    }))->toBeEmpty();

    expect($view->render(function () {
        //
    }))->toBe('contents');
});

test('render sections returns environment sections', function () {
    $view = Mockery::mock(View::class.'[render]', [
        Mockery::mock(Factory::class),
        Mockery::mock(Engine::class),
        'view',
        'path',
        [],
    ]);

    $view->expects('render')->with(Mockery::type(Closure::class))->andReturn($sections = ['foo' => 'bar']);

    expect($view->renderSections())->toEqual($sections);
});

test('sections are not flushed when not done rendering', function () {
    $view = getView(['foo' => 'bar']);
    $view->getFactory()->expects('incrementRender')->times(2);
    $view->getFactory()->expects('callComposer')->times(2)->with($view);
    $view->getFactory()->expects('getShared')->times(2)->andReturn(['shared' => 'foo']);
    $view->getEngine()->expects('get')->times(2)->with('path', ['foo' => 'bar', 'shared' => 'foo'])->andReturn('contents');
    $view->getFactory()->expects('decrementRender')->times(2);
    $view->getFactory()->expects('flushStateIfDoneRendering')->times(2);

    expect($view->render())->toBe('contents');
    expect((string) $view)->toBe('contents');
});

test('view nest binds asub view', function () {
    $view = getView();
    $view->getFactory()->expects('make')->with('foo', ['data']);
    $result = $view->nest('key', 'foo', ['data']);

    expect($result)->toBeInstanceOf(View::class);
});

test('view accepts arrayable implementations', function () {
    $arrayable = new class implements Arrayable
    {
        public function toArray()
        {
            return ['foo' => 'bar', 'baz' => ['qux', 'corge']];
        }
    };

    $view = getView($arrayable);

    expect($view->foo)->toBe('bar');
    expect($view->baz)->toEqual(['qux', 'corge']);
});

test('view getters setters', function () {
    $view = getView(['foo' => 'bar']);
    expect($view->name())->toBe('view');
    expect($view->getPath())->toBe('path');
    $data = $view->getData();
    expect($data['foo'])->toBe('bar');
    $view->setPath('newPath');
    expect($view->getPath())->toBe('newPath');
});

test('view array access', function () {
    $view = getView(['foo' => 'bar']);
    expect($view)->toBeInstanceOf(ArrayAccess::class);
    expect($view->offsetExists('foo'))->toBeTrue();
    expect($view->offsetGet('foo'))->toBe('bar');
    $view->offsetSet('foo', 'baz');
    expect($view->offsetGet('foo'))->toBe('baz');
    $view->offsetUnset('foo');
    expect($view->offsetExists('foo'))->toBeFalse();
});

test('view constructed with object data', function () {
    $view = getView(new DataObjectStub);
    expect($view)->toBeInstanceOf(ArrayAccess::class);
    expect($view->offsetExists('foo'))->toBeTrue();
    expect($view->offsetGet('foo'))->toBe('bar');
    $view->offsetSet('foo', 'baz');
    expect($view->offsetGet('foo'))->toBe('baz');
    $view->offsetUnset('foo');
    expect($view->offsetExists('foo'))->toBeFalse();
});

test('view magic methods', function () {
    $view = getView(['foo' => 'bar']);
    expect(isset($view->foo))->toBeTrue();
    expect($view->foo)->toBe('bar');
    $view->foo = 'baz';
    expect($view->foo)->toBe('baz');
    expect($view->foo)->toEqual($view['foo']);
    unset($view->foo);
    expect(isset($view->foo))->toBeFalse();
    expect($view->offsetExists('foo'))->toBeFalse();
});

test('view bad method', function () {

    $view = getView();
    $view->badMethodCall();
})->throws(BadMethodCallException::class, 'Method Mariner\View\View::badMethodCall does not exist.');

test('view gather data with renderable', function () {
    $view = getView();
    $view->getFactory()->expects('incrementRender')->ordered();
    $view->getFactory()->expects('callComposer')->ordered()->with($view);
    $view->getFactory()->expects('getShared')->andReturn(['shared' => 'foo']);
    $view->getEngine()->expects('get')->andReturn('contents');
    $view->getFactory()->expects('decrementRender')->ordered();
    $view->getFactory()->expects('flushStateIfDoneRendering');

    $view->renderable = Mockery::mock(Renderable::class);
    $view->renderable->expects('render')->andReturn('text');
    expect($view->render())->toBe('contents');
});

test('view render sections', function () {
    $view = getView();
    $view->getFactory()->expects('incrementRender')->ordered();
    $view->getFactory()->expects('callComposer')->ordered()->with($view);
    $view->getFactory()->expects('getShared')->andReturn(['shared' => 'foo']);
    $view->getEngine()->expects('get')->andReturn('contents');
    $view->getFactory()->expects('decrementRender')->ordered();
    $view->getFactory()->expects('flushStateIfDoneRendering');

    $view->getFactory()->expects('getSections')->andReturn(['foo', 'bar']);
    $sections = $view->renderSections();
    expect($sections[0])->toBe('foo');
    expect($sections[1])->toBe('bar');
});

test('with errors', function () {
    $view = getView();
    $errors = ['foo' => 'bar', 'qu' => 'ux'];
    expect($view->withErrors($errors))->toBe($view);
    expect($view->errors)->toBeInstanceOf(ViewErrorBag::class);
    $foo = $view->errors->get('foo');
    expect($foo[0])->toBe('bar');
    $qu = $view->errors->get('qu');
    expect($qu[0])->toBe('ux');
    $data = ['foo' => 'baz'];
    expect($view->withErrors(new MessageBag($data)))->toBe($view);
    $foo = $view->errors->get('foo');
    expect($foo[0])->toBe('baz');
    $foo = $view->errors->getBag('default')->get('foo');
    expect($foo[0])->toBe('baz');
    expect($view->withErrors(new MessageBag($data), 'login'))->toBe($view);
    $foo = $view->errors->getBag('login')->get('foo');
    expect($foo[0])->toBe('baz');
});

function getView($data = [])
{
    return new View(
        Mockery::mock(Factory::class),
        Mockery::mock(Engine::class),
        'view',
        'path',
        $data
    );
}

class DataObjectStub
{
    public $foo = 'bar';
}
