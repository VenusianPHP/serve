<?php

namespace Venusian\Serve\Tests\View\Blade;



test('stack is compiled', function () {
    $string = '@stack(\'foo\')';
    $expected = '<?php echo $__env->yieldPushContent(\'foo\'); ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = '@stack(\'foo))\')';
    $expected = '<?php echo $__env->yieldPushContent(\'foo))\'); ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
