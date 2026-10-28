<?php

namespace Venusian\Serve\Tests\View\Blade;

use Voyager\NutsAndBolts\Str;

test('push is compiled', function () {
    $string = '@push(\'foo\')
test
@endpush';
    $expected = '<?php $__env->startPush(\'foo\'); ?>
test
<?php $__env->stopPush(); ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('push is compiled with parenthesis', function () {
    $string = '@push(\'foo):))\')
test
@endpush';
    $expected = '<?php $__env->startPush(\'foo):))\'); ?>
test
<?php $__env->stopPush(); ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('push once is compiled', function () {
    $string = '@pushOnce(\'foo\', \'bar\')
test
@endPushOnce';

    $expected = '<?php if (! $__env->hasRenderedOnce(\'bar\')): $__env->markAsRenderedOnce(\'bar\');
$__env->startPush(\'foo\'); ?>
test
<?php $__env->stopPush(); endif; ?>';

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('push once is compiled when id is missing', function () {
    Str::createUuidsUsing(fn () => 'e60e8f77-9ac3-4f71-9f8e-a044ef481d7f');

    $string = '@pushOnce(\'foo\')
test
@endPushOnce';

    $expected = '<?php if (! $__env->hasRenderedOnce(\'e60e8f77-9ac3-4f71-9f8e-a044ef481d7f\')): $__env->markAsRenderedOnce(\'e60e8f77-9ac3-4f71-9f8e-a044ef481d7f\');
$__env->startPush(\'foo\'); ?>
test
<?php $__env->stopPush(); endif; ?>';

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('push if is compiled', function () {
    $string = '@pushIf(true, \'foo\')
test
@endPushIf';
    $expected = '<?php if(true): $__env->startPush( \'foo\'); ?>
test
<?php $__env->stopPush(); endif; ?>';

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('push if with more than one comma is compiled', function () {
    $string = '@pushIf(Str::startsWith(\'abc\', \'a\'), \'body-end\')
test
@endPushIf';

    $expected = '<?php if(Str::startsWith(\'abc\', \'a\')): $__env->startPush(\'body-end\'); ?>
test
<?php $__env->stopPush(); endif; ?>';

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('push if with comma in string is compiled', function () {
    $string = '@pushIf(Str::startsWith(\'abc,,,\', \'a,,,\'), \'body-end\')
test
@endPushIf';

    $expected = '<?php if(Str::startsWith(\'abc,,,\', \'a,,,\')): $__env->startPush(\'body-end\'); ?>
test
<?php $__env->stopPush(); endif; ?>';

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('push if else is compiled', function () {
    $string = '@pushIf(true, \'stack\')
if
@elsePushIf(false, \'stack\')
elseif
@elsePush(\'stack\')
else
@endPushIf';
    $expected = '<?php if(true): $__env->startPush( \'stack\'); ?>
if
<?php $__env->stopPush(); elseif(false): $__env->startPush( \'stack\'); ?>
elseif
<?php $__env->stopPush(); else: $__env->startPush(\'stack\'); ?>
else
<?php $__env->stopPush(); endif; ?>';

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('else push if with more than one comma is compiled', function () {
    $string = '@pushIf(true, \'stack\')
if
@elsePushIf(in_array($x, [3, 4]), \'scripts\')
elseif
@endPushIf';
    $expected = '<?php if(true): $__env->startPush( \'stack\'); ?>
if
<?php $__env->stopPush(); elseif(in_array($x, [3, 4])): $__env->startPush(\'scripts\'); ?>
elseif
<?php $__env->stopPush(); endif; ?>';

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('else push if with comma in string is compiled', function () {
    $string = '@pushIf(true, \'stack\')
if
@elsePushIf(Str::startsWith(\'abc,,,\', \'a,,,\'), \'body-end\')
elseif
@endPushIf';
    $expected = '<?php if(true): $__env->startPush( \'stack\'); ?>
if
<?php $__env->stopPush(); elseif(Str::startsWith(\'abc,,,\', \'a,,,\')): $__env->startPush(\'body-end\'); ?>
elseif
<?php $__env->stopPush(); endif; ?>';

    expect($this->compiler->compileString($string))->toEqual($expected);
});
