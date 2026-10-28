<?php

namespace Venusian\Serve\Tests\View\Blade;



test('statement that contains non consecutive parenthesis are compiled', function () {
    $string = "Foo @lang(function_call('foo(blah)')) bar";
    $expected = "Foo <?php echo app('translator')->get(function_call('foo(blah)')); ?> bar";
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('language and choices are compiled', function () {
    expect($this->compiler->compileString("@lang('foo')"))->toBe('<?php echo app(\'translator\')->get(\'foo\'); ?>');
    expect($this->compiler->compileString("@choice('foo', 1)"))->toBe('<?php echo app(\'translator\')->choice(\'foo\', 1); ?>');
});
