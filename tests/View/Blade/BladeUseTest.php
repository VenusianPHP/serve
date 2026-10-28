<?php

namespace Venusian\Serve\Tests\View\Blade;



test('use statements are compiled', function () {
    $expected = "Foo <?php use \SomeNamespace\SomeClass as Foo; ?> bar";

    $string = "Foo @use('SomeNamespace\SomeClass', 'Foo') bar";
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "Foo @use(SomeNamespace\SomeClass, Foo) bar";
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('use statements without as are compiled', function () {
    $expected = "Foo <?php use \SomeNamespace\SomeClass; ?> bar";

    $string = "Foo @use('SomeNamespace\SomeClass') bar";
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "Foo @use(SomeNamespace\SomeClass) bar";
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('use statements with backslash at beginning are compiled', function () {
    $expected = "Foo <?php use \SomeNamespace\SomeClass; ?> bar";

    $string = "Foo @use('\SomeNamespace\SomeClass') bar";
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "Foo @use(\SomeNamespace\SomeClass) bar";
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('use statements with backslash at beginning and aliased are compiled', function () {
    $expected = "Foo <?php use \SomeNamespace\SomeClass as Foo; ?> bar";

    $string = "Foo @use('\SomeNamespace\SomeClass', 'Foo') bar";
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "Foo @use(\SomeNamespace\SomeClass, Foo) bar";
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('use statements with braces are compiled correctly', function () {
    $expected = "Foo <?php use \SomeNamespace\{Foo, Bar}; ?> bar";

    $string = "Foo @use('SomeNamespace\{Foo, Bar}') bar";
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "Foo @use(SomeNamespace\{Foo, Bar}) bar";
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('use statement with braces and backslash are compiled correctly', function () {
    $expected = "Foo <?php use \SomeNamespace\{Foo, Bar}; ?> bar";

    $string = "Foo @use('\SomeNamespace\{Foo, Bar}') bar";
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "Foo @use(\SomeNamespace\{Foo, Bar}) bar";
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('use statements with modifiers are compiled', function () {
    $expected = 'Foo <?php use function \SomeNamespace\SomeFunction as Foo; ?> bar';

    $string = "Foo @use('function SomeNamespace\SomeFunction', 'Foo') bar";
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = 'Foo @use(function SomeNamespace\SomeFunction, Foo) bar';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('use statements with modifiers without alias are compiled', function () {
    $expected = 'Foo <?php use const \SomeNamespace\SOME_CONST; ?> bar';

    $string = "Foo @use('const SomeNamespace\SOME_CONST') bar";
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = 'Foo @use(const SomeNamespace\SOME_CONST) bar';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('use statements with modifiers and backslash at beginning are compiled', function () {
    $expected = 'Foo <?php use function \SomeNamespace\SomeFunction; ?> bar';

    $string = "Foo @use('function \SomeNamespace\SomeFunction') bar";
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = 'Foo @use(function \SomeNamespace\SomeFunction) bar';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('use statements with modifiers backslash at beginning and aliased are compiled', function () {
    $expected = 'Foo <?php use const \SomeNamespace\SOME_CONST as Foo; ?> bar';

    $string = "Foo @use('const \SomeNamespace\SOME_CONST', 'Foo') bar";
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = 'Foo @use(const \SomeNamespace\SOME_CONST, Foo) bar';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('use statements with modifiers with braces are compiled correctly', function () {
    $expected = 'Foo <?php use function \SomeNamespace\{Foo, Bar}; ?> bar';

    $string = "Foo @use('function SomeNamespace\{Foo, Bar}') bar";
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = 'Foo @use(function SomeNamespace\{Foo, Bar}) bar';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('use function statement with braces and backslash are compiled correctly', function () {
    $expected = 'Foo <?php use const \SomeNamespace\{FOO, BAR}; ?> bar';

    $string = "Foo @use('const \SomeNamespace\{FOO, BAR}') bar";
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = 'Foo @use(const \SomeNamespace\{FOO, BAR}) bar';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
