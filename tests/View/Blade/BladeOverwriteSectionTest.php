<?php

namespace Venusian\Serve\Tests\View\Blade;



test('overwrite sections are compiled', function () {
    expect($this->compiler->compileString('@overwrite'))->toBe('<?php $__env->stopSection(true); ?>');
});
