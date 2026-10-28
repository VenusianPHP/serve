<?php

namespace Venusian\Serve\Tests\View\Blade;

use Exception;
use Voyager\NutsAndBolts\Fluent;
use Voyager\NutsAndBolts\DataObjects\Stringable;

beforeEach(function () {

    $this->compiler->stringable(function (Fluent $object) {
        return 'Hello World';
    });
});

test('blade handler can intercept regular echos', function () {
    expect($this->compiler->compileString('{{$exampleObject}}'))->toBe("<?php \$__bladeCompiler = app('blade.compiler'); ?><?php echo e(\$__bladeCompiler->applyEchoHandler(\$exampleObject)); ?>");
});

test('blade handler can intercept raw echos', function () {
    expect($this->compiler->compileString('{!!$exampleObject!!}'))->toBe("<?php \$__bladeCompiler = app('blade.compiler'); ?><?php echo \$__bladeCompiler->applyEchoHandler(\$exampleObject); ?>");
});

test('blade handler can intercept escaped echos', function () {
    expect($this->compiler->compileString('{{{$exampleObject}}}'))->toBe("<?php \$__bladeCompiler = app('blade.compiler'); ?><?php echo e(\$__bladeCompiler->applyEchoHandler(\$exampleObject)); ?>");
});

test('whitespace is preserved correctly', function () {
    expect($this->compiler->compileString("{{\$exampleObject}}\n"))->toBe("<?php \$__bladeCompiler = app('blade.compiler'); ?><?php echo e(\$__bladeCompiler->applyEchoHandler(\$exampleObject)); ?>\n\n");
});

test('handler logic works correctly', function ($blade) {

    $this->compiler->stringable(Fluent::class, function ($object) {
        throw new Exception('The fluent object has been successfully handled!');
    });

    app()->registerInstance('blade.compiler', $this->compiler);

    $exampleObject = new Fluent();

    eval((new Stringable($this->compiler->compileString($blade)))->remove(['<?php', '?>']));
})->with('handlerLogicDataProvider')->throws(Exception::class, 'The fluent object has been successfully handled!');

dataset('handlerLogicDataProvider', function () {
    return [
        ['{{$exampleObject}}'],
        ['{{$exampleObject;}}'],
        ['{{{$exampleObject;}}}'],
        ['{!!$exampleObject;!!}'],
    ];
});

test('handler works with iterables', function ($blade, $closure, $expectedOutput) {
    $this->compiler->stringable('iterable', $closure);

    app()->registerInstance('blade.compiler', $this->compiler);

    ob_start();
    eval((new Stringable($this->compiler->compileString($blade)))->remove(['<?php', '?>']));
    $output = ob_get_contents();
    ob_end_clean();

    expect($output)->toBe($expectedOutput);
})->with('handlerWorksWithIterableDataProvider');

dataset('handlerWorksWithIterableDataProvider', function () {
    return [
        ['{{[1,"two",3]}}', function (iterable $arr) {
            return implode(', ', $arr);
        }, '1, two, 3'],
    ];
});

test('handler works with non stringables', function ($blade, $expectedOutput) {
    app()->registerInstance('blade.compiler', $this->compiler);

    ob_start();
    eval((new Stringable($this->compiler->compileString($blade)))->remove(['<?php', '?>']));
    $output = ob_get_contents();
    ob_end_clean();

    expect($output)->toBe($expectedOutput);
})->with('nonStringableDataProvider');

dataset('nonStringableDataProvider', function () {
    return [
        ['{{"foo" . "bar"}}', 'foobar'],
        ['{{ 1 + 2 }}{{ "test"; }}', '3test'],
        ['@php($test = "hi"){{ $test }}', 'hi'],
        ['{!! "&nbsp;" !!}', '&nbsp;'],
    ];
});
