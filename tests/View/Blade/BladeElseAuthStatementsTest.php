<?php

namespace Venusian\Serve\Tests\View\Blade;



test('else auth statements are compiled', function () {
    $string = '@auth("api")
breeze
@elseauth("standard")
wheeze
@endauth';
    $expected = '<?php if(auth()->guard("api")->check()): ?>
breeze
<?php elseif(auth()->guard("standard")->check()): ?>
wheeze
<?php endif; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('plain else auth statements are compiled', function () {
    $string = '@auth("api")
breeze
@elseauth
wheeze
@endauth';
    $expected = '<?php if(auth()->guard("api")->check()): ?>
breeze
<?php elseif(auth()->guard()->check()): ?>
wheeze
<?php endif; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
