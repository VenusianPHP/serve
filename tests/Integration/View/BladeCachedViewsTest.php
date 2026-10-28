<?php

namespace Venusian\Serve\Tests\Integration\View;

use Mariner\NutsAndBolts\EncodedHtmlString;
use Mariner\NutsAndBolts\Facades\Blade;
use Mariner\NutsAndBolts\Facades\View;
use function Voyager\Filesystem\join_paths;

afterEach(function () {
    EncodedHtmlString::flushState();

});

test('compiled path differs when echo format changes', function () {
    $compiler = Blade::getFacadeRoot();
    $path = join_paths(__DIR__, 'templates', 'uses-link.blade.php');

    $default = $compiler->getCompiledPath($path);

    $custom = $compiler->usingEchoFormat(
        'new \Mariner\NutsAndBolts\EncodedHtmlString(%s)',
        fn () => $compiler->getCompiledPath($path)
    );

    expect($custom)->not->toBe($default);
    expect($compiler->getCompiledPath($path))->toBe($default);
    expect($custom)->toEndWith('.php');
});

test('cached view is not reused for a different echo format', function () {
    $compiler = Blade::getFacadeRoot();
    $path = join_paths(__DIR__, 'templates', 'echo-format.blade.php');

    @mkdir(dirname($path), 0777, true);
    file_put_contents($path, '{{ $value }}');

    try {
        $value = '[link](https://evil.example)';

        // Compile with the default echo format, as "view:cache" does.
        $compiler->compile($path);
        expect($default = $compiler->getCompiledPath($path))->toBeFile();

        $rendered = $compiler->usingEchoFormat(
            'new \Mariner\NutsAndBolts\EncodedHtmlString(%s)',
            function () use ($compiler, $path, $default, $value) {
                EncodedHtmlString::encodeUsing(fn ($string) => 'SECURED:'.$string);

                expect($compiler->getCompiledPath($path))->not->toBe($default);

                return View::file($path, ['value' => $value])->render();
            }
        );

        expect($rendered)->toBe('SECURED:'.$value);
    } finally {
        @unlink($path);
    }
});
