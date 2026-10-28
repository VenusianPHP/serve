<?php

namespace Venusian\Serve\Tests\View\Blade;



test('append sections are compiled', function () {
    expect($this->compiler->compileString('@append'))->toBe('<?php $__env->appendSection(); ?>');
});
