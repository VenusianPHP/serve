<?php

namespace Venusian\Serve\Tests\View\Blade;



test('verbatim blocks are compiled', function () {
    $string = '@verbatim {{ $a }} @if($b) {{ $b }} @endif @endverbatim';
    $expected = ' {{ $a }} @if($b) {{ $b }} @endif ';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('verbatim blocks with multiple lines are compiled', function () {
    $string = 'Some text
@verbatim
    {{ $a }}
    @if($b)
        {{ $b }}
    @endif
@endverbatim';
    $expected = 'Some text

    {{ $a }}
    @if($b)
        {{ $b }}
    @endif
';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('multiple verbatim blocks are compiled', function () {
    $string = '@verbatim {{ $a }} @endverbatim {{ $b }} @verbatim {{ $c }} @endverbatim';
    $expected = ' {{ $a }}  <?php echo e($b); ?>  {{ $c }} ';
    expect($this->compiler->compileString($string))->toEqual($expected);
});

test('raw blocks are rendered in the right order', function () {
    $string = '@php echo "#1"; @endphp @verbatim {{ #2 }} @endverbatim @verbatim {{ #3 }} @endverbatim @php echo "#4"; @endphp';

    $expected = '<?php echo "#1"; ?>  {{ #2 }}   {{ #3 }}  <?php echo "#4"; ?>';

    expect($this->compiler->compileString($string))->toBe($expected);
});

test('multiline templates with raw blocks are rendered in the right order', function () {
    $string = '{{ $first }}
@php
    echo $second;
@endphp
@if ($conditional)
    {{ $third }}
@endif
@include("users")
@verbatim
    {{ $fourth }} @include("test")
@endverbatim
@php echo $fifth; @endphp';

    $expected = '<?php echo e($first); ?>

<?php
    echo $second;
?>
<?php if($conditional): ?>
    <?php echo e($third); ?>

<?php endif; ?>
<?php echo $__env->make("users", array_diff_key(get_defined_vars(), [\'__data\' => 1, \'__path\' => 1]))->render(); ?>

    {{ $fourth }} @include("test")

<?php echo $fifth; ?>';

    expect($this->compiler->compileString($string))->toBe($expected);
});

test('raw blocks dont get mixed up when some are removed by blade comments', function () {
    $string = '{{-- @verbatim Block #1 @endverbatim --}} @php "Block #2" @endphp';
    $expected = ' <?php "Block #2" ?>';

    expect($this->compiler->compileString($string))->toBe($expected);
});

test('newlines are inserted correctly after echo', function () {
    $string = "test @verbatim\nhello world\n@endverbatim";
    $expected = "test \nhello world\n";
    expect($this->compiler->compileString($string))->toBe($expected);

    $string = "{{ 1 }}\nhello world\n";
    $expected = "<?php echo e(1); ?>\n\nhello world\n";
    expect($this->compiler->compileString($string))->toBe($expected);

    $string = "{{ 1 }}@verbatim\nhello world\n@endverbatim";
    $expected = "<?php echo e(1); ?>\n\nhello world\n";
    expect($this->compiler->compileString($string))->toBe($expected);
});
