<?php

namespace Venusian\Serve\Tests\View\Blade;



test('escaped with at directives are compiled', function () {
    expect($this->compiler->compileString('@@foreach'))->toBe('@foreach');
    expect($this->compiler->compileString('@@verbatim @@continue @@endverbatim'))->toBe('@verbatim @continue @endverbatim');
    expect($this->compiler->compileString('@@foreach($i as $x)'))->toBe('@foreach($i as $x)');
    expect($this->compiler->compileString('@@continue @@break'))->toBe('@continue @break');
    expect($this->compiler->compileString('@@foreach(
            $i as $x
        )'))->toBe('@foreach(
            $i as $x
        )');
});

test('nested escapes', function () {
    $template = '
@foreach($cols as $col)
    @@foreach($issues as $issue_45915)
        👋 سلام 👋
    @@endforeach
@endforeach';
    $compiled = '
<?php $__currentLoopData = $cols; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $col): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
    @foreach($issues as $issue_45915)
        👋 سلام 👋
    @endforeach
<?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>';
    expect($this->compiler->compileString($template))->toBe($compiled);
});
