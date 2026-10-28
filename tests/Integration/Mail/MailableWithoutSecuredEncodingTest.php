<?php

namespace Venusian\Serve\Tests\Integration\Mail;

use Mariner\Auth\User;
use Mariner\Mail\Mailable;
use Mariner\Mail\Markdown;
use Mariner\Mail\Mailables\Content;
use Mariner\Mail\Mailables\Envelope;
use Venusian\Serve\Tests\Integration\Fixtures\UserFactory;
use Venusian\Serve\Tests\Integration\IntegrationTestCase;
use Voyager\Database\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeAll(fn () => IntegrationTestCase::withMigrations('laravel'));
afterAll(fn () => IntegrationTestCase::withMigrations());

beforeEach(function () {
    $this->app['view']->addLocation(__DIR__.'/Fixtures');

    Markdown::withoutSecuredEncoding();
});

test('it can assert markdown encoded string using template', function ($given, $expected) {
    $user = UserFactory::new()->create([
        'name' => $given,
    ]);

    $mailable = new class($user) extends Mailable
    {
        public $theme = 'taylor';

        public function __construct(public User $user)
        {
            //
        }

        public function build()
        {
            return $this->markdown('message-with-template');
        }
    };

    $mailable->assertSeeInHtml($expected, false);
})->with('markdownEncodedTemplateDataProviderPlain');

test('it can assert markdown encoded string using template with table', function ($given, $expected) {
    $user = UserFactory::new()->create([
        'name' => $given,
    ]);

    $mailable = new class($user) extends Mailable
    {
        public $theme = 'taylor';

        public function __construct(public User $user)
        {
            //
        }

        public function build()
        {
            return $this->markdown('table-with-template');
        }
    };

    $mailable->assertSeeInHtml($expected, false);
    $mailable->assertSeeInHtml('<p>This is a subcopy</p>', false);
    $mailable->assertSeeInHtml(<<<'TABLE'
<table>
<thead>
<tr>
<th>Laravel</th>
<th align="center">Table</th>
<th align="right">Example</th>
</tr>
</thead>
<tbody>
<tr>
<td>Col 2 is</td>
<td align="center">Centered</td>
<td align="right">$10</td>
</tr>
<tr>
<td>Col 3 is</td>
<td align="center">Right-Aligned</td>
<td align="right">$20</td>
</tr>
</tbody>
</table>
TABLE, false);
})->with('markdownEncodedTemplateDataProviderPlain');

dataset('markdownEncodedTemplateDataProviderPlain', function () {
    yield ['[Laravel](https://laravel.com)', '<p><em>Hi</em> <a href="https://laravel.com">Laravel</a></p>'];

    yield [
        '![Welcome to Laravel](https://laravel.com/assets/img/welcome/background.svg)',
        '<p><em>Hi</em> <img src="https://laravel.com/assets/img/welcome/background.svg" alt="Welcome to Laravel"></p>',
    ];

    yield [
        'Visit https://laravel.com/docs to browse the documentation',
        '<em>Hi</em> Visit https://laravel.com/docs to browse the documentation',
    ];

    yield [
        'Visit <https://laravel.com/docs> to browse the documentation',
        '<em>Hi</em> Visit &lt;https://laravel.com/docs&gt; to browse the documentation',
    ];

    yield [
        'Visit <span>https://laravel.com/docs</span> to browse the documentation',
        '<em>Hi</em> Visit &lt;span&gt;https://laravel.com/docs&lt;/span&gt; to browse the documentation',
    ];
});

test('it can assert markdown encoded string', function ($given, $expected) {
    $mailable = new class($given) extends Mailable
    {
        public function __construct(public string $message)
        {
            //
        }

        public function envelope()
        {
            return new Envelope(
                subject: 'My basic title',
            );
        }

        public function content()
        {
            return new Content(
                markdown: 'message',
            );
        }
    };

$mailable->assertSeeInHtml($expected, false);
})->with('markdownEncodedDataProviderPlain');

dataset('markdownEncodedDataProviderPlain', function () {
    yield ['[Laravel](https://laravel.com)', 'My message is: [Laravel](https://laravel.com)'];

    yield [
        '![Welcome to Laravel](https://laravel.com/assets/img/welcome/background.svg)',
        'My message is: ![Welcome to Laravel](https://laravel.com/assets/img/welcome/background.svg)',
    ];

    yield [
        'Visit https://laravel.com/docs to browse the documentation',
        'My message is: Visit https://laravel.com/docs to browse the documentation',
    ];

    yield [
        'Visit <https://laravel.com/docs> to browse the documentation',
        'My message is: Visit &lt;https://laravel.com/docs&gt; to browse the documentation',
    ];

    yield [
        'Visit <span>https://laravel.com/docs</span> to browse the documentation',
        'My message is: Visit &lt;span&gt;https://laravel.com/docs&lt;/span&gt; to browse the documentation',
    ];
});
