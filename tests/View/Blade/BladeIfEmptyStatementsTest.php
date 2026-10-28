<?php

namespace Venusian\Serve\Tests\View\Blade;



test('if statements are compiled', function () {
    $string = '@empty ($test)
breeze
@endempty';
    $expected = '<?php if(empty($test)): ?>
breeze
<?php endif; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
