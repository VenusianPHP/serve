<?php

namespace Venusian\Serve\Tests\View\Blade;



test('break statements are compiled', function () {
    $string = '@for ($i = 0; $i < 10; $i++)
test
@break
@endfor';
    $expected = '<?php for($i = 0; $i < 10; $i++): ?>
test
<?php break; ?>
<?php endfor; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('break statements with expression are compiled', function () {
    $string = '@for ($i = 0; $i < 10; $i++)
test
@break(TRUE)
@endfor';
    $expected = '<?php for($i = 0; $i < 10; $i++): ?>
test
<?php if(TRUE) break; ?>
<?php endfor; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('break statements with argument are compiled', function () {
    $string = '@for ($i = 0; $i < 10; $i++)
test
@break(2)
@endfor';
    $expected = '<?php for($i = 0; $i < 10; $i++): ?>
test
<?php break 2; ?>
<?php endfor; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('break statements with spaced argument are compiled', function () {
    $string = '@for ($i = 0; $i < 10; $i++)
test
@break( 2 )
@endfor';
    $expected = '<?php for($i = 0; $i < 10; $i++): ?>
test
<?php break 2; ?>
<?php endfor; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('break statements with faulty argument are compiled', function () {
    $string = '@for ($i = 0; $i < 10; $i++)
test
@break(-2)
@endfor';
    $expected = '<?php for($i = 0; $i < 10; $i++): ?>
test
<?php break 1; ?>
<?php endfor; ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});
