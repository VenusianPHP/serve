<?php

namespace Venusian\Serve\Tests\View\Blade;



test('stop sections are compiled', function () {
    expect($this->compiler->compileString('@stop'))->toBe('<?php $__env->stopSection(); ?>');
});
