<?php

namespace Venusian\Serve\Tests\View\Concerns;

use Mariner\View\Concerns\ManagesStacks;
use Mariner\View\RenderState;

test('stack is empty', function () {
    expect((new FakeViewFactory)->isStackEmpty('my-stack'))->toBeTrue();
});

test('stack is not empty with pushed content', function () {
    $object = new FakeViewFactory;
    $object->startPush('my-stack', 'some pushed content');

    expect($object->isStackEmpty('my-stack'))->toBeFalse();
});

test('stack is not empty with prepended content', function () {
    $object = new FakeViewFactory;
    $object->startPrepend('my-stack', 'some prepended content');

    expect($object->isStackEmpty('my-stack'))->toBeFalse();
});

class FakeViewFactory
{
    use ManagesStacks;

    protected int $renderCount = 0;

    private ?RenderState $state = null;

    protected function renderState(): RenderState
    {
        return $this->state ??= new RenderState;
    }
}
