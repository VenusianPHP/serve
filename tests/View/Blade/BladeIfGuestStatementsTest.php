<?php

namespace Venusian\Serve\Tests\View\Blade;



test('if statements are compiled', function () {
    $string = '@guest("api")
breeze
@endguest';
    $expected = '<?php if(auth()->guard("api")->guest()): ?>
breeze
<?php endif; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
