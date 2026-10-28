<?php

namespace Venusian\Serve\Tests\Integration\Mail;

use Mariner\Mail\Markdown;
use Mariner\NutsAndBolts\EncodedHtmlString;
use Voyager\NutsAndBolts\HtmlString;

afterEach(function () {
    Markdown::flushState();
    EncodedHtmlString::flushState();

});

test('it can parse markdown string', function ($given, $expected) {
    tap(Markdown::parse($given), function ($html) use ($expected) {
        expect($html)->toBeInstanceOf(HtmlString::class);

        expect(withUnixLineEndings((string) $html))->toBe(withUnixLineEndings($expected.PHP_EOL));
        expect((string) $html->toHtml())->toBe((string) $html);
    });
})->with('markdownDataProvider');

test('it can parse markdown encoded string', function ($given, $expected) {
    tap(Markdown::parse($given, encoded: true), function ($html) use ($expected) {
        expect($html)->toBeInstanceOf(HtmlString::class);

        expect(withUnixLineEndings((string) $html))->toBe(withUnixLineEndings($expected.PHP_EOL));
    });
})->with('markdownEncodedDataProvider');

dataset('markdownDataProvider', function () {
    yield ['[Laravel](https://laravel.com)', '<p><a href="https://laravel.com">Laravel</a></p>'];
    yield ['\[Laravel](https://laravel.com)', '<p>[Laravel](https://laravel.com)</p>'];
    yield ['![Welcome to Laravel](https://laravel.com/assets/img/welcome/background.svg)', '<p><img src="https://laravel.com/assets/img/welcome/background.svg" alt="Welcome to Laravel" /></p>'];
    yield ['!\[Welcome to Laravel](https://laravel.com/assets/img/welcome/background.svg)', '<p>![Welcome to Laravel](https://laravel.com/assets/img/welcome/background.svg)</p>'];
    yield ['Visit https://laravel.com/docs to browse the documentation', '<p>Visit https://laravel.com/docs to browse the documentation</p>'];
    yield ['Visit <https://laravel.com/docs> to browse the documentation', '<p>Visit <a href="https://laravel.com/docs">https://laravel.com/docs</a> to browse the documentation</p>'];
    yield ['Visit <span>https://laravel.com/docs</span> to browse the documentation', '<p>Visit <span>https://laravel.com/docs</span> to browse the documentation</p>'];
});

dataset('markdownEncodedDataProvider', function () {
    yield [new EncodedHtmlString('[Laravel](https://laravel.com)'), '<p>[Laravel](https://laravel.com)</p>'];

    yield [
        new EncodedHtmlString('![Welcome to Laravel](https://laravel.com/assets/img/welcome/background.svg)'),
        '<p>![Welcome to Laravel](https://laravel.com/assets/img/welcome/background.svg)</p>',
    ];

    yield [
        new EncodedHtmlString('Visit https://laravel.com/docs to browse the documentation'),
        '<p>Visit https://laravel.com/docs to browse the documentation</p>',
    ];

    yield [
        new EncodedHtmlString('Visit <https://laravel.com/docs> to browse the documentation'),
        '<p>Visit &lt;https://laravel.com/docs&gt; to browse the documentation</p>',
    ];

    yield [
        new EncodedHtmlString('Visit <span>https://laravel.com/docs</span> to browse the documentation'),
        '<p>Visit &lt;span&gt;https://laravel.com/docs&lt;/span&gt; to browse the documentation</p>',
    ];

    yield [new EncodedHtmlString('\[Laravel](https://laravel.com)'), '<p>\[Laravel](https://laravel.com)</p>'];

    yield [
        new EncodedHtmlString('!\[Welcome to Laravel](https://laravel.com/assets/img/welcome/background.svg)'),
        '<p>!\[Welcome to Laravel](https://laravel.com/assets/img/welcome/background.svg)</p>',
    ];

    yield [
        new EncodedHtmlString(new HtmlString('Visit <span>https://laravel.com/docs</span> to browse the documentation')),
        '<p>Visit <span>https://laravel.com/docs</span> to browse the documentation</p>',
    ];

    yield [
        '![Welcome to Laravel](https://laravel.com/assets/img/welcome/background.svg)<br />'.new EncodedHtmlString('Visit <span>https://laravel.com/docs</span> to browse the documentation'),
        '<p><img src="https://laravel.com/assets/img/welcome/background.svg" alt="Welcome to Laravel" /><br />Visit &lt;span&gt;https://laravel.com/docs&lt;/span&gt; to browse the documentation</p>',
    ];
});

test('it can parse markdown with custom extensions via config', function () {
    configureMarkdownExtensions([
        \League\CommonMark\Extension\Strikethrough\StrikethroughExtension::class,
    ]);

    tap(Markdown::parse('~~strikethrough text~~'), function ($html) {
        expect($html)->toBeInstanceOf(HtmlString::class);

        $expected = '<p><del>strikethrough text</del></p>';

        expect(withUnixLineEndings((string) $html))->toBe(withUnixLineEndings($expected.PHP_EOL));
        expect((string) $html->toHtml())->toBe((string) $html);
    });
});

test('it can parse markdown without custom extensions does not apply them', function () {
    configureMarkdownExtensions([]);

    tap(Markdown::parse('~~strikethrough text~~'), function ($html) {
        expect($html)->toBeInstanceOf(HtmlString::class);

        $expected = '<p>~~strikethrough text~~</p>';

        expect(withUnixLineEndings((string) $html))->toBe(withUnixLineEndings($expected.PHP_EOL));
        expect((string) $html->toHtml())->toBe((string) $html);
    });
});

test('it can parse markdown with multiple custom extensions', function () {
    configureMarkdownExtensions([
        \League\CommonMark\Extension\Strikethrough\StrikethroughExtension::class,
        \League\CommonMark\Extension\TaskList\TaskListExtension::class,
    ]);

    tap(Markdown::parse('~~strikethrough~~'), function ($html) {
        expect($html)->toBeInstanceOf(HtmlString::class);

        $expected = '<p><del>strikethrough</del></p>';

        expect(withUnixLineEndings((string) $html))->toBe(withUnixLineEndings($expected.PHP_EOL));
        expect((string) $html->toHtml())->toBe((string) $html);
    });

    tap(Markdown::parse('- [ ] Task item'), function ($html) {
        expect($html)->toBeInstanceOf(HtmlString::class);

        $expected = "<ul>\n<li><input disabled=\"\" type=\"checkbox\"> Task item</li>\n</ul>";

        expect(withUnixLineEndings((string) $html))->toBe(withUnixLineEndings($expected.PHP_EOL));
        expect((string) $html->toHtml())->toBe((string) $html);
    });
});

test('it can parse markdown encoded string with custom extensions', function () {
    configureMarkdownExtensions([
        \League\CommonMark\Extension\Strikethrough\StrikethroughExtension::class,
    ]);

    tap(Markdown::parse(new EncodedHtmlString('~~strikethrough text~~'), encoded: true), function ($html) {
        expect($html)->toBeInstanceOf(HtmlString::class);

        $expected = '<p><del>strikethrough text</del></p>';

        expect(withUnixLineEndings((string) $html))->toBe(withUnixLineEndings($expected.PHP_EOL));
    });
});

function withUnixLineEndings(string $text): string
{
    return str_replace(["\r\n", "\r"], "\n", $text);
}

function configureMarkdownExtensions(array $extensions): void
{
    app('config')->set('mail.markdown.extensions', $extensions);

    // A fresh app per test has not resolved Markdown yet, so this builds it with the extensions just configured.
    app()->make(Markdown::class);
}
