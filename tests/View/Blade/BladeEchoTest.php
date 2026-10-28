<?php

namespace Venusian\Serve\Tests\View\Blade;



test('echos are compiled', function () {
    expect($this->compiler->compileString('{!!$name!!}'))->toBe('<?php echo $name; ?>');
    expect($this->compiler->compileString('{!! $name !!}'))->toBe('<?php echo $name; ?>');
    expect($this->compiler->compileString('{!!
            $name
        !!}'))->toBe('<?php echo $name; ?>');

    expect($this->compiler->compileString('{{{$name}}}'))->toBe('<?php echo e($name); ?>');
    expect($this->compiler->compileString('{{$name}}'))->toBe('<?php echo e($name); ?>');
    expect($this->compiler->compileString('{{ $name }}'))->toBe('<?php echo e($name); ?>');
    expect($this->compiler->compileString('{{
            $name
        }}'))->toBe('<?php echo e($name); ?>');
    expect($this->compiler->compileString("{{ \$name }}\n"))->toBe("<?php echo e(\$name); ?>\n\n");
    expect($this->compiler->compileString("{{ \$name }}\r\n"))->toBe("<?php echo e(\$name); ?>\r\n\r\n");

    expect($this->compiler->compileString('{{ "Hello world or foo" }}'))->toBe('<?php echo e("Hello world or foo"); ?>');
    expect($this->compiler->compileString('{{"Hello world or foo"}}'))->toBe('<?php echo e("Hello world or foo"); ?>');
    expect($this->compiler->compileString('{{$foo + $or + $baz}}'))->toBe('<?php echo e($foo + $or + $baz); ?>');
    expect($this->compiler->compileString('{{
            "Hello world or foo"
        }}'))->toBe('<?php echo e("Hello world or foo"); ?>');

    expect($this->compiler->compileString('{{ \'Hello world or foo\' }}'))->toBe('<?php echo e(\'Hello world or foo\'); ?>');
    expect($this->compiler->compileString('{{\'Hello world or foo\'}}'))->toBe('<?php echo e(\'Hello world or foo\'); ?>');
    expect($this->compiler->compileString('{{
            \'Hello world or foo\'
        }}'))->toBe('<?php echo e(\'Hello world or foo\'); ?>');

    expect($this->compiler->compileString('{{ myfunc(\'foo or bar\') }}'))->toBe('<?php echo e(myfunc(\'foo or bar\')); ?>');
    expect($this->compiler->compileString('{{ myfunc("foo or bar") }}'))->toBe('<?php echo e(myfunc("foo or bar")); ?>');
    expect($this->compiler->compileString('{{ myfunc("$name or \'foo\'") }}'))->toBe('<?php echo e(myfunc("$name or \'foo\'")); ?>');
});

test('escaped with at echos are compiled', function () {
    expect($this->compiler->compileString('@{{$name}}'))->toBe('{{$name}}');
    expect($this->compiler->compileString('@{{ $name }}'))->toBe('{{ $name }}');
    expect($this->compiler->compileString('@{{
            $name
        }}'))->toBe('{{
            $name
        }}');
    expect($this->compiler->compileString('@{{ $name }}
            '))->toBe('{{ $name }}
            ');
});
