<?php

namespace Venusian\Serve\Tests\View\Blade;



test('section starts are compiled', function () {
    expect($this->compiler->compileString('@section(\'foo\')'))->toBe('<?php $__env->startSection(\'foo\'); ?>');
    expect($this->compiler->compileString('@section(\'issue#18317 :))\')'))->toBe('<?php $__env->startSection(\'issue#18317 :))\'); ?>');
    expect($this->compiler->compileString('@section(name(foo))'))->toBe('<?php $__env->startSection(name(foo)); ?>');
});
