<?php

namespace Venusian\Serve\Tests\View\Blade;



test('bool', function () {
    // For Javascript object{'isBool' : true}
    $string = "{'isBool' : @bool(true)}";
    $expected = "{'isBool' : <?php echo ((true) ? 'true' : 'false'); ?>}";
    expect($this->compiler->compileString($string))->toEqual($expected);

    // For Javascript object{'isBool' : false}
    $string = "{'isBool' : @bool(false)}";
    $expected = "{'isBool' : <?php echo ((false) ? 'true' : 'false'); ?>}";
    expect($this->compiler->compileString($string))->toEqual($expected);

    // For Alpine.js x-show attribute
    $string = "<input type='text' x-show='@bool(true)' />";
    $expected = "<input type='text' x-show='<?php echo ((true) ? 'true' : 'false'); ?>' />";
    expect($this->compiler->compileString($string))->toEqual($expected);

    // For Alpine.js x-show attribute
    $string = "<input type='text' x-show='@bool(false)' />";
    $expected = "<input type='text' x-show='<?php echo ((false) ? 'true' : 'false'); ?>' />";
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('compile bool', function () {
    $someViewVarTruthy = 123;
    $compiled = $this->compiler->compileString('@bool($someViewVarTruthy)');

    ob_start();
    eval(substr($compiled, 6, -3));
    expect(ob_get_clean())->toBe('true');

    $someViewVarFalsey = '0';
    $compiled = $this->compiler->compileString('@bool($someViewVarFalsey)');

    ob_start();
    eval(substr($compiled, 6, -3));
    expect(ob_get_clean())->toBe('false');

    $anotherSomeViewVarTruthy = new SomeClass();
    $compiled = $this->compiler->compileString('@bool($anotherSomeViewVarTruthy)');

    ob_start();
    eval(substr($compiled, 6, -3));
    expect(ob_get_clean())->toBe('true');

    $anotherSomeViewVarFalsey = null;
    $compiled = $this->compiler->compileString('@bool($anotherSomeViewVarFalsey)');

    ob_start();
    eval(substr($compiled, 6, -3));
    expect(ob_get_clean())->toBe('false');
});

class SomeClass
{
    public function someMethod()
    {
    }
}
