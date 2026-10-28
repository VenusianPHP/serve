<?php

namespace Venusian\Serve\Tests\View\Blade;



test('classes are conditionally compiled from array', function () {
    $string = "<span @class(['font-bold', 'mt-4', 'ml-2' => true, 'mr-2' => false])></span>";
    $expected = "<span class=\"<?php echo \Voyager\NutsAndBolts\DataObjects\Arr::toCssClasses(['font-bold', 'mt-4', 'ml-2' => true, 'mr-2' => false]); ?>\"></span>";

    expect($this->compiler->compileString($string))->toEqual($expected);
});
