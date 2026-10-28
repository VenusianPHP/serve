<?php

namespace Venusian\Serve\Tests\View\Blade;



test('php statements with expression are compiled', function () {
    $string = '@php($set = true)';
    $expected = '<?php ($set = true); ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('string with parenthesis with end php', function () {
    $string = "@php(\$data = ['related_to' => 'issue#45388'];) {{ \$data }} @endphp";
    $expected = "<?php(\$data = ['related_to' => 'issue#45388'];) {{ \$data }} ?>";

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('php statements without expression are ignored', function () {
    $string = '@php';
    $expected = '@php';
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = '{{ "Ignore: @php" }}';
    $expected = '<?php echo e("Ignore: @php"); ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('php statements dont parse blade code', function () {
    $string = '@php echo "{{ This is a blade tag }}" @endphp';
    $expected = '<?php echo "{{ This is a blade tag }}" ?>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('verbatim and php statements dont get mixed up', function () {
    $string = "@verbatim {{ Hello, I'm not blade! }}"
            ."\n@php echo 'And I'm not PHP!' @endphp"
            ."\n@endverbatim {{ 'I am Blade' }}"
            ."\n@php echo 'I am PHP {{ not Blade }}' @endphp";

    $expected = " {{ Hello, I'm not blade! }}"
            ."\n@php echo 'And I'm not PHP!' @endphp"
            ."\n <?php echo e('I am Blade'); ?>"
            ."\n\n<?php echo 'I am PHP {{ not Blade }}' ?>";

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('string with opening parenthesis can be compiled', function () {
    $string = "@php(\$data = ['single' => ':(('])";
    $expected = "<?php (\$data = ['single' => ':((']); ?>";
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "@php(\$data = ['single' => (string)':(('])";
    $expected = "<?php (\$data = ['single' => (string)':((']); ?>";
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "@php(\$data = ['single' => '(()(('])";
    $expected = "<?php (\$data = ['single' => '(()((']); ?>";
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('string with parenthesis can be compiled', function () {
    $string = "@php(\$data = ['single' => ')'])";
    $expected = "<?php (\$data = ['single' => ')']); ?>";

    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "@php(\$data = ['(multiple)-))' => '((-))'])";
    $expected = "<?php (\$data = ['(multiple)-))' => '((-))']); ?>";

    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "@php(\$data = [(int)'(multiple)-))' => (bool)'((casty))'])";
    $expected = "<?php (\$data = [(int)'(multiple)-))' => (bool)'((casty))']); ?>";

    expect($this->compiler->compileString($string))->toEqual($expected);

    expect($this->compiler->compileString('@each(\'foo\', \'b)a)r\')'))->toBe('<?php echo $__env->renderEach(\'foo\', \'b)a)r\'); ?>');
    expect($this->compiler->compileString('@include(\'test_for\', [\'issue))\' => \'(issue#45424))\'])'))->toBe('<?php echo $__env->make(\'test_for\', [\'issue))\' => \'(issue#45424))\'], array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>');
    expect($this->compiler->compileString('( @include(\'test_for\', [\'not_too_much))\' => \'(issue#45424))\'])))'))->toBe('( <?php echo $__env->make(\'test_for\', [\'not_too_much))\' => \'(issue#45424))\'], array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>))');
});

test('string with empty string data value', function () {
    $string = "@php(\$data = ['test' => ''])";

    $expected = "<?php (\$data = ['test' => '']); ?>";

    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "@php(\$data = ['test' => \"\"])";

    $expected = "<?php (\$data = ['test' => \"\"]); ?>";

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('string with escaping data value', function () {
    $string = "@php(\$data = ['test' => 'won\\'t break'])";

    $expected = "<?php (\$data = ['test' => 'won\\'t break']); ?>";

    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "@php(\$data = ['test' => \"\\\"escaped\\\"\"])";

    $expected = "<?php (\$data = ['test' => \"\\\"escaped\\\"\"]); ?>";

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('unclosed parenthesis for blade tags', function () {
    $string = "<span @class(['(']></span>";
    $expected = "<span class=\"<?php echo \Voyager\NutsAndBolts\DataObjects\Arr::toCssClasses([]); ?>\"(['(']></span>";

    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "<span @class(['']></span>";
    $expected = "<span class=\"<?php echo \Voyager\NutsAndBolts\DataObjects\Arr::toCssClasses([]); ?>\"(['']></span>";

    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "<span @class([')']></span>";
    $expected = "<span @class([')']></span>";

    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "<span @class(['))']></span>";
    $expected = "<span @class(['))']></span>";

    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('nested tag calls', function () {
    $string = "<span @class(['k' => @empty(\$v)])></span>";
    $expected = '<span class="<?php echo \Voyager\NutsAndBolts\DataObjects\Arr::toCssClasses([\'k\' => @empty($v)]); ?>"></span>';
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "<span @class(['k))' => @empty(\$v)])></span>";
    $expected = '<span class="<?php echo \Voyager\NutsAndBolts\DataObjects\Arr::toCssClasses([\'k))\' => @empty($v)]); ?>"></span>';
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "<span @class(['k' => @empty(\$v), 't' => @empty(\$v1)])></span>";
    $expected = '<span class="<?php echo \Voyager\NutsAndBolts\DataObjects\Arr::toCssClasses([\'k\' => @empty($v), \'t\' => @empty($v1)]); ?>"></span>';
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "<span @class(['k' => @empty(\$v), 't' => @empty(\$v1), 'r' => @empty(\$v2)])></span>";
    $expected = '<span class="<?php echo \Voyager\NutsAndBolts\DataObjects\Arr::toCssClasses([\'k\' => @empty($v), \'t\' => @empty($v1), \'r\' => @empty($v2)]); ?>"></span>';
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "<span @class(['k' => @empty(\$v), 't))' => @empty(\$v1), 'r' => @empty(\$v2)])></span>";
    $expected = '<span class="<?php echo \Voyager\NutsAndBolts\DataObjects\Arr::toCssClasses([\'k\' => @empty($v), \'t))\' => @empty($v1), \'r\' => @empty($v2)]); ?>"></span>';
    expect($this->compiler->compileString($string))->toEqual($expected);

    $string = "<span @class(['k' => @empty(\$v), 't' => @empty(\$v1), 'r' => @empty(\$v2), 'l' => 'l'])></span><span @class(['k' => @empty(\$v)])></span>";
    $expected = '<span class="<?php echo \Voyager\NutsAndBolts\DataObjects\Arr::toCssClasses([\'k\' => @empty($v), \'t\' => @empty($v1), \'r\' => @empty($v2), \'l\' => \'l\']); ?>"></span><span class="<?php echo \Voyager\NutsAndBolts\DataObjects\Arr::toCssClasses([\'k\' => @empty($v)]); ?>"></span>';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('it does not compile invalid syntax', function () {
    $template = "<a @class(['k\' => ()])></a>";
    expect($this->compiler->compileString($template))->toEqual($template);
});
