<?php

namespace Venusian\Serve\Tests\Integration\View;

use Mariner\NutsAndBolts\Facades\Blade;
use Mariner\NutsAndBolts\Facades\View;
use InvalidArgumentException;

beforeEach(function () {
    $app = $this->app;
    $app['config']->set('view.paths', [__DIR__.'/Fixtures/anonymous-components-templates']);
});

test('anonymous components with custom paths can be rendered', function () {
    Blade::anonymousComponentPath(__DIR__.'/Fixtures/anonymous-components-1', 'layouts');
    Blade::anonymousComponentPath(__DIR__.'/Fixtures/anonymous-components-2');

    $view = View::make('page')->render();

    expect($view)->toContain('Panel content.');
    expect($view)->toContain('class="app-layout"');
    expect($view)->toContain('class="danger-button"');
});

test('anonymous components with custom paths cant be rendered as normal views', function () {

    Blade::anonymousComponentPath(__DIR__.'/Fixtures/anonymous-components-1', 'layouts');
    Blade::anonymousComponentPath(__DIR__.'/Fixtures/anonymous-components-2');

    View::make('layouts::app')->render();
})->throws(InvalidArgumentException::class);

test('anonymous components with custom paths cant be rendered as normal views even with no prefix', function () {

    Blade::anonymousComponentPath(__DIR__.'/Fixtures/anonymous-components-1', 'layouts');
    Blade::anonymousComponentPath(__DIR__.'/Fixtures/anonymous-components-2');

    View::make('panel')->render();
})->throws(InvalidArgumentException::class);
