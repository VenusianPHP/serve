<?php

namespace Venusian\Serve\Tests\View\Blade;



test('if statements are compiled', function () {
    $string = '@auth("api")
breeze
@endauth';
    $expected = '<?php if(auth()->guard("api")->check()): ?>
breeze
<?php endif; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('plain if statements are compiled', function () {
    $string = '@auth
breeze
@endauth';
    $expected = '<?php if(auth()->guard()->check()): ?>
breeze
<?php endif; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
