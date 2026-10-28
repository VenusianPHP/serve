<?php

namespace Venusian\Serve\Tests\View\Blade;



test('sessions are compiled', function () {
    $string = '
@session(\'status\')
    <span>{{ $value }}</span>
@endsession';
    $expected = '
<?php $__sessionArgs = [\'status\'];
if (session()->has($__sessionArgs[0])) :
if (isset($value)) { $__sessionPrevious[] = $value; }
$value = session()->get($__sessionArgs[0]); ?>
    <span><?php echo e($value); ?></span>
<?php unset($value);
if (isset($__sessionPrevious) && !empty($__sessionPrevious)) { $value = array_pop($__sessionPrevious); }
if (isset($__sessionPrevious) && empty($__sessionPrevious)) { unset($__sessionPrevious); }
endif;
unset($__sessionArgs); ?>';

    expect($this->compiler->compileString($string))->toEqual($expected);
});
