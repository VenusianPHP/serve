<?php

namespace Venusian\Serve\Tests\View\Blade;



test('canany statements are compiled', function () {
    $string = '@canany ([\'create\', \'update\'], [$post])
breeze
@elsecanany([\'delete\', \'approve\'], [$post])
sneeze
@endcan';
    $expected = '<?php if (app(\\Mariner\\Contracts\\Auth\\Access\\Gate::class)->any([\'create\', \'update\'], [$post])): ?>
breeze
<?php elseif (app(\\Mariner\\Contracts\\Auth\\Access\\Gate::class)->any([\'delete\', \'approve\'], [$post])): ?>
sneeze
<?php endif; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
