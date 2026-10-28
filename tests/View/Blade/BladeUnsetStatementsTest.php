<?php

namespace Venusian\Serve\Tests\View\Blade;



test('unset statements are compiled', function () {
    $string = '@unset ($unset)';
    $expected = '<?php unset($unset); ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = '@unset ($unset)))';
    $expected = '<?php unset($unset); ?>))';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
