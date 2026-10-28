<?php

namespace Venusian\Serve\Tests\View\Blade;



test('cannot statements are compiled', function () {
    $string = '@cannot (\'update\', [$post])
breeze
@elsecannot(\'delete\', [$post])
sneeze
@endcannot';
    $expected = '<?php if (app(\\Mariner\\Contracts\\Auth\\Access\\Gate::class)->denies(\'update\', [$post])): ?>
breeze
<?php elseif (app(\\Mariner\\Contracts\\Auth\\Access\\Gate::class)->denies(\'delete\', [$post])): ?>
sneeze
<?php endif; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
