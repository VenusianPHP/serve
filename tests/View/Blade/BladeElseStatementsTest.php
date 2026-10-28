<?php

namespace Venusian\Serve\Tests\View\Blade;



test('else statements are compiled', function () {
    $string = '@if (name(foo(bar)))
breeze
@else
boom
@endif';
    $expected = '<?php if(name(foo(bar))): ?>
breeze
<?php else: ?>
boom
<?php endif; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
