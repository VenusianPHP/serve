<?php

namespace Venusian\Serve\Tests\View\Blade;



test('section missing statements are compiled', function () {
    $string = '@sectionMissing("section")
breeze
@endif';
    $expected = '<?php if (empty(trim($__env->yieldContent("section")))): ?>
breeze
<?php endif; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
