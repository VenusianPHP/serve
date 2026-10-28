<?php

namespace Venusian\Serve\Tests\View\Blade;



test('shows are compiled', function () {
    expect($this->compiler->compileString('@show'))->toBe('<?php echo $__env->yieldSection(); ?>');
});
