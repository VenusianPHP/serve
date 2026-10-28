<?php

namespace Venusian\Serve\Tests\View\Blade;



test('can statements are compiled', function () {
    $string = '@can (\'update\', [$post])
breeze
@elsecan(\'delete\', [$post])
sneeze
@endcan';
    $expected = '<?php if (app(\\Mariner\\Contracts\\Auth\\Access\\Gate::class)->check(\'update\', [$post])): ?>
breeze
<?php elseif (app(\\Mariner\\Contracts\\Auth\\Access\\Gate::class)->check(\'delete\', [$post])): ?>
sneeze
<?php endif; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
