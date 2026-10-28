<?php

namespace Venusian\Serve\Tests\View\Blade;



test('end sections are compiled', function () {
    expect($this->compiler->compileString('@endsection'))->toBe('<?php $__env->stopSection(); ?>');
});
