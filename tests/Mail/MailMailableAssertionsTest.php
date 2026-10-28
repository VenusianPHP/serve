<?php

namespace Venusian\Serve\Tests\Mail;

use Mariner\Mail\Mailable;
use PHPUnit\Framework\AssertionFailedError;

test('mailable assert see in text passes when present', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertSeeInText('First Item');
});

test('mailable assert see in text fails when absent', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertSeeInText('Fourth Item');
})->throws(AssertionFailedError::class);

test('mailable assert dont see in text passes when absent', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertDontSeeInText('Fourth Item');
});

test('mailable assert dont see in text fails when present', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertDontSeeInText('First Item');
})->throws(AssertionFailedError::class);

test('mailable assert see in html passes when present', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertSeeInHtml('Fourth & Fifth Item');

    $mailable->assertSeeInHtml('<li>First Item</li>', false);
});

test('mailable assert see in html fails when absent', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertSeeInHtml('<li>Fourth Item</li>');
})->throws(AssertionFailedError::class);

test('mailable assert dont see in html passes when absent', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertDontSeeInHtml('<li>Fourth Item</li>');
});

test('mailable assert dont see in html escaped fails when present', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertDontSeeInHtml('Fourth & Fifth Item');
})->throws(AssertionFailedError::class);

test('mailable assert dont see in html unescaped fails when present', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertDontSeeInHtml('<li>First Item</li>', false);
})->throws(AssertionFailedError::class);

test('mailable assert see in order text passes when present in order', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertSeeInOrderInText([
        'First Item',
        'Second Item',
        'Third Item',
    ]);
});

test('mailable assert see in order text fails when absent in order', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertSeeInOrderInText([
        'First Item',
        'Third Item',
        'Second Item',
    ]);
})->throws(AssertionFailedError::class);

test('mailable assert in order html passes when present in order', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertSeeInOrderInHtml([
        'Third Item',
        'Fourth & Fifth Item',
        'Sixth Item',
    ]);

    $mailable->assertSeeInOrderInHtml([
        '<li>First Item</li>',
        '<li>Second Item</li>',
        '<li>Third Item</li>',
    ], false);
});

test('mailable assert in order html fails when absent in order', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertSeeInOrderInHtml([
        '<li>Second Item</li>',
        '<li>First Item</li>',
        '<li>Third Item</li>',
    ]);
})->throws(AssertionFailedError::class);

test('mailable assert see in text with apostrophe passes when present', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertSeeInText("It's a wonderful day");
});

test('mailable assert see in text with apostrophe fails when absent', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertSeeInText("It's not a wonderful day");
})->throws(AssertionFailedError::class);

test('mailable assert dont see in text with apostrophe passes when absent', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertDontSeeInText("It's not a wonderful day");
});

test('mailable assert dont see in text with apostrophe fails when present', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertDontSeeInText("It's a wonderful day");
})->throws(AssertionFailedError::class);

test('mailable assert see in html with apostrophe fails when absent', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertSeeInHtml("<li>It's not a wonderful day</li>");
})->throws(AssertionFailedError::class);

test('mailable assert dont see in html with apostrophe passes when absent', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertDontSeeInHtml("<li>It's not a wonderful day</li>");
});

test('mailable assert dont see in html with apostrophe fails when present', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertDontSeeInHtml("<li>It's a wonderful day</li>", false);
})->throws(AssertionFailedError::class);

test('mailable assert see in html with blade escaped apostrophe passes when present', function () {
    $mailable = new MailableAssertionsBladeEscapedStub;

    $mailable->assertSeeInHtml("It's a wonderful day");
});

test('mailable assert see in order in html with apostrophe passes when present in order', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertSeeInOrderInHtml([
        'First Item',
        'Sixth Item',
        'It\'s a wonderful day',
    ]);

    $mailable->assertSeeInOrderInHtml([
        '<li>First Item</li>',
        '<li>It\'s a wonderful day</li>',
    ], false);
});

test('mailable assert see in order in html with apostrophe fails when absent in order', function () {
    $mailable = new MailableAssertionsStub;

    $mailable->assertSeeInOrderInHtml([
        'It\'s a wonderful day',
        'First Item',
        'Sixth Item',
    ]);
})->throws(AssertionFailedError::class);

class MailableAssertionsStub extends Mailable
{
    protected function renderForAssertions()
    {
        $text = <<<'EOD'
        # List
        - First Item
        - Second Item
        - Third Item
        - Fourth & Fifth Item
        - Sixth Item
        - It's a wonderful day
        EOD;

        $html = <<<'EOD'
        <!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Transitional//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-transitional.dtd">
        <html xmlns="http://www.w3.org/1999/xhtml">
        <head>
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
        </style>
        </head>
        <body>
        <h1>List</h1>
        <ul>
        <li>First Item</li>
        <li>Second Item</li>
        <li>Third Item</li>
        <li>Fourth &amp; Fifth Item</li>
        <li>Sixth Item</li>
        <li>It's a wonderful day</li>
        </ul>
        </body>
        </html>
        EOD;

        return [$html, $text];
    }
}

class MailableAssertionsBladeEscapedStub extends Mailable
{
    protected function renderForAssertions()
    {
        $text = "It's a wonderful day";

        $html = <<<'EOD'
        <!DOCTYPE html>
        <html>
        <body>
        <div>It&#039;s a wonderful day</div>
        </body>
        </html>
        EOD;

        /**
         * Since stub override `renderForAssertions()` we should expect that `$html` is available from either `$this->view` or `$this->markdown`.
         */

        return [$html, $text];
    }
}
