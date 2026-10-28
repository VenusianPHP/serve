<?php

namespace Venusian\Serve\Tests\View\Blade;



test('eachs are compiled', function () {
    expect($this->compiler->compileString('@each(\'foo\', \'bar\')'))->toBe('<?php echo $__env->renderEach(\'foo\', \'bar\'); ?>');
    expect($this->compiler->compileString('@each(\'foo\', \'(bar))\')'))->toBe('<?php echo $__env->renderEach(\'foo\', \'(bar))\'); ?>');
    expect($this->compiler->compileString('@each(name(foo))'))->toBe('<?php echo $__env->renderEach(name(foo)); ?>');
});

test('includes are compiled', function () {
    expect($this->compiler->compileString('@include(\'foo\')'))->toBe('<?php echo $__env->make(\'foo\', array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>');
    expect($this->compiler->compileString('@include(\'foo\', [\'((\'])'))->toBe('<?php echo $__env->make(\'foo\', [\'((\'], array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>');
    expect($this->compiler->compileString('@include(\'foo\', [\'((a)\' => \'((a)\'])'))->toBe('<?php echo $__env->make(\'foo\', [\'((a)\' => \'((a)\'], array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>');
    expect($this->compiler->compileString('@include(name(foo))'))->toBe('<?php echo $__env->make(name(foo), array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>');
});

test('include ifs are compiled', function () {
    expect($this->compiler->compileString('@includeIf(\'foo\')'))->toBe('<?php if ($__env->exists(\'foo\')) echo $__env->make(\'foo\', array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>');
    expect($this->compiler->compileString('@includeIf(name(foo))'))->toBe('<?php if ($__env->exists(name(foo))) echo $__env->make(name(foo), array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>');
});

test('include whens are compiled', function () {
    expect($this->compiler->compileString('@includeWhen(true, \'foo\', ["foo" => "bar"])'))->toBe('<?php echo $__env->renderWhen(true, \'foo\', ["foo" => "bar"], array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1])); ?>');
    expect($this->compiler->compileString('@includeWhen(true, \'foo\')'))->toBe('<?php echo $__env->renderWhen(true, \'foo\', array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1])); ?>');
});

test('include unlesses are compiled', function () {
    expect($this->compiler->compileString('@includeUnless(true, \'foo\', ["foo" => "bar"])'))->toBe('<?php echo $__env->renderUnless(true, \'foo\', ["foo" => "bar"], array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1])); ?>');
    expect($this->compiler->compileString('@includeUnless(true, \'foo\', ["foo" => "bar_))-))>"])'))->toBe('<?php echo $__env->renderUnless(true, \'foo\', ["foo" => "bar_))-))>"], array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1])); ?>');
    expect($this->compiler->compileString('@includeUnless($undefined ?? true, \'foo\')'))->toBe('<?php echo $__env->renderUnless($undefined ?? true, \'foo\', array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1])); ?>');
});

test('include firsts are compiled', function () {
    expect($this->compiler->compileString('@includeFirst(["one", "two"])'))->toBe('<?php echo $__env->first(["one", "two"], array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>');
    expect($this->compiler->compileString('@includeFirst(["one", "two"], ["foo" => "bar"])'))->toBe('<?php echo $__env->first(["one", "two"], ["foo" => "bar"], array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>');
    expect($this->compiler->compileString('@includeFirst(["issue", "#45424)"], ["foo()" => "bar)-))"])'))->toBe('<?php echo $__env->first(["issue", "#45424)"], ["foo()" => "bar)-))"], array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>');
    expect($this->compiler->compileString('@includeFirst(["issue", "#45424)"], ["foo" => "bar(-(("])'))->toBe('<?php echo $__env->first(["issue", "#45424)"], ["foo" => "bar(-(("], array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>');
    expect($this->compiler->compileString('@includeFirst(["issue", "#45424)"], [(string) "foo()" => "bar(-(("])'))->toBe('<?php echo $__env->first(["issue", "#45424)"], [(string) "foo()" => "bar(-(("], array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>');
});

test('include scopeds are compiled', function () {
    expect($this->compiler->compileString('@includeIsolated(\'foo\')'))->toBe('<?php echo $__env->make(\'foo\')->render(); ?>');
    expect($this->compiler->compileString('@includeIsolated(\'foo\', [\'((\'])'))->toBe('<?php echo $__env->make(\'foo\', [\'((\'])->render(); ?>');
    expect($this->compiler->compileString('@includeIsolated(\'foo\', [\'((a)\' => \'((a)\'])'))->toBe('<?php echo $__env->make(\'foo\', [\'((a)\' => \'((a)\'])->render(); ?>');
    expect($this->compiler->compileString('@includeIsolated(name(foo))'))->toBe('<?php echo $__env->make(name(foo))->render(); ?>');
});
