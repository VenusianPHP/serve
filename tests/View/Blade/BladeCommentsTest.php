<?php

namespace Venusian\Serve\Tests\View\Blade;



test('comments are compiled', function () {
    $string = '{{--this is a comment--}}';
    expect($this->compiler->compileString($string))->toBeEmpty();

    $string = '{{--
this is a comment
--}}';
    expect($this->compiler->compileString($string))->toBeEmpty();

    $string = sprintf('{{-- this is an %s long comment --}}', str_repeat('extremely ', 1000));
    expect($this->compiler->compileString($string))->toBeEmpty();
});

test('blade code inside comments is not compiled', function () {
    $string = '{{-- @foreach() --}}';

    expect($this->compiler->compileString($string))->toBeEmpty();
});
