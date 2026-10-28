<?php

namespace Venusian\Serve\Tests\View\Blade;



test('extends are compiled', function () {
    $string = "@extends('foo')\ntest";
    $expected = "test\n".'<?php echo $__env->make(\'foo\', array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = '@extends(name(foo))'."\n".'test';
    $expected = "test\n".'<?php echo $__env->make(name(foo), array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('sequential compile string calls', function () {
    $string = "@extends('foo')\ntest";
    $expected = "test\n".'<?php echo $__env->make(\'foo\', array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);

    // use the same compiler instance to compile another template with @extends directive
    $string = "@extends(name(foo))\ntest";
    $expected = "test\n".'<?php echo $__env->make(name(foo), array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('extends first are compiled', function () {
    $string = "@extendsFirst(['foo', 'milwad'])\ntest";
    $expected = "test\n".'<?php echo $__env->first([\'foo\', \'milwad\'], array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = '@extendsFirst([name(foo), name(milwad)])'."\n".'test';
    $expected = "test\n".'<?php echo $__env->first([name(foo), name(milwad)], array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
