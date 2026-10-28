<?php

namespace Venusian\Serve\Tests\View\Blade;



test('selected statements are compiled', function () {
    $string = '<input @selected(name(foo(bar)))/>';
    $expected = "<input <?php if(name(foo(bar))): echo 'selected'; endif; ?>/>";

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('checked statements are compiled', function () {
    $string = '<input @checked(name(foo(bar)))/>';
    $expected = "<input <?php if(name(foo(bar))): echo 'checked'; endif; ?>/>";

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('disabled statements are compiled', function () {
    $string = '<button @disabled(name(foo(bar)))>Foo</button>';
    $expected = "<button <?php if(name(foo(bar))): echo 'disabled'; endif; ?>>Foo</button>";

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('required statements are compiled', function () {
    $string = '<input @required(name(foo(bar)))/>';
    $expected = "<input <?php if(name(foo(bar))): echo 'required'; endif; ?>/>";

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('readonly statements are compiled', function () {
    $string = '<input @readonly(name(foo(bar)))/>';
    $expected = "<input <?php if(name(foo(bar))): echo 'readonly'; endif; ?>/>";

    expect($this->compiler->compileString($string))->toEqual($expected);
});
