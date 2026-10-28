<?php

namespace Venusian\Serve\Tests\View\Blade;



test('styles are conditionally compiled from array', function () {
    $string = "<span @style(['font-weight: bold', 'text-decoration: underline', 'color: red' => true, 'margin-top: 10px' => false])></span>";
    $expected = "<span style=\"<?php echo \Voyager\NutsAndBolts\DataObjects\Arr::toCssStyles(['font-weight: bold', 'text-decoration: underline', 'color: red' => true, 'margin-top: 10px' => false]) ?>\"></span>";

    expect($this->compiler->compileString($string))->toEqual($expected);
});
