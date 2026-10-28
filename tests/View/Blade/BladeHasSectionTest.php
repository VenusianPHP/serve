<?php

namespace Venusian\Serve\Tests\View\Blade;



test('has section statements are compiled', function () {
    $string = '@hasSection("section")
breeze
@endif';
    $expected = '<?php if (! empty(trim($__env->yieldContent("section")))): ?>
breeze
<?php endif; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
