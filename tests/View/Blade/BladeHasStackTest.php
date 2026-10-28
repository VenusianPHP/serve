<?php

namespace Venusian\Serve\Tests\View\Blade;



test('has stack statements are compiled', function () {
    $string = '@hasStack("stack")
breeze
@endif';
    $expected = '<?php if (! $__env->isStackEmpty("stack")): ?>
breeze
<?php endif; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
