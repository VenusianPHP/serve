<?php

namespace Venusian\Serve\Tests\View\Blade;



test('if statements are compiled', function () {
    $string = '@guest("api")
breeze
@elseguest("standard")
wheeze
@endguest';
    $expected = '<?php if(auth()->guard("api")->guest()): ?>
breeze
<?php elseif(auth()->guard("standard")->guest()): ?>
wheeze
<?php endif; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
