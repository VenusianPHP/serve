<?php

namespace Venusian\Serve\Tests\View\Blade;



test('statement is compiled with safe default encoding options', function () {
    $string = 'var foo = @json($var);';
    $expected = 'var foo = <?php echo json_encode($var, 15, 512) ?>;';

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('encoding options can be overwritten', function () {
    $string = 'var foo = @json($var, JSON_HEX_TAG);';
    $expected = 'var foo = <?php echo json_encode($var, JSON_HEX_TAG, 512) ?>;';

    expect($this->compiler->compileString($string))->toEqual($expected);
});
