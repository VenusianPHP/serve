<?php

namespace Venusian\Serve\Tests\View\Blade;



test('errors are compiled', function () {
    $string = '
@error(\'email\')
    <span>{{ $message }}</span>
@enderror';
    $expected = '
<?php $__errorArgs = [\'email\'];
$__bag = $errors->getBag($__errorArgs[1] ?? \'default\');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
    <span><?php echo e($message); ?></span>
<?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>';

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('errors with bags are compiled', function () {
    $string = '
@error(\'email\', \'customBag\')
    <span>{{ $message }}</span>
@enderror';
    $expected = '
<?php $__errorArgs = [\'email\', \'customBag\'];
$__bag = $errors->getBag($__errorArgs[1] ?? \'default\');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
    <span><?php echo e($message); ?></span>
<?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
