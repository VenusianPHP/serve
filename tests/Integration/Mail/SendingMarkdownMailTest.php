<?php

namespace Venusian\Serve\Tests\Integration\Mail;

use Mariner\Mail\Mailable;
use Mariner\Mail\Mailables\Content;
use Mariner\Mail\Mailables\Envelope;
use Mariner\Mail\Markdown;
use Mariner\NutsAndBolts\Facades\Mail;
use Voyager\NutsAndBolts\DataObjects\Stringable;

beforeEach(function () {
    $app = $this->app;
    $app['config']->set('mail.driver', 'array');

    $app['view']->addNamespace('mail', __DIR__.'/Fixtures')
        ->addLocation(__DIR__.'/Fixtures');
});

test('mail is sent', function () {
    $mailable = new BasicMailable();

    $mailable
        ->assertHasSubject('My basic title')
        ->assertSeeInText('My basic content')
        ->assertSeeInHtml('My basic content');
});

test('mail may have specific text view', function () {
    $mailable = new BasicMailableWithTextView();

    $mailable
        ->assertHasSubject('My basic title')
        ->assertSeeInHtml('My basic content')
        ->assertSeeInText('My basic text view')
        ->assertDontSeeInText('My basic content');
});

test('embed', function () {
    Mail::to('test@mail.com')->send($mailable = new EmbedMailable());

    $mailable->assertSeeInHtml('Embed content: cid:');
    $mailable->assertSeeInText('Embed content: ');
    $mailable->assertDontSeeInText('Embed content: cid:');

    $email = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage()->toString();

    $cid = rtrim(explode(' cid:', (new Stringable($email))->explode("\r\n")
        ->filter(fn ($line) => str_contains($line, ' content: cid:'))
        ->first())[1], '=');

    $filename = explode('Embed file: ', (new Stringable($email))->explode("\r\n")
        ->filter(fn ($line) => str_contains($line, ' file:'))
        ->first())[1];

    // The HTML view is compiled with a different echo format, so its compiled filename has a suffix.
    $hash = explode('.', $filename)[0];

    expect($email)->toContain('Content-Type: application/x-php;');
    expect($email)->toContain($hash);
    expect($email)->toContain('Content-ID: <');
});

test('embed data', function () {
    Mail::to('test@mail.com')->send($mailable = new EmbedDataMailable());

    $mailable->assertSeeInText('Embed data content: ');
    $mailable->assertSeeInHtml('Embed data content: cid:');

    $email = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage()->toString();

    expect($email)->toContain(<<<EOT
        Content-Type: image/png; name=foo.jpg\r
        Content-Transfer-Encoding: base64\r
        Content-Disposition: inline; name=foo.jpg; filename=foo.jpg\r
        EOT);
});

test('embed multiline image', function () {
    Mail::to('test@mail.com')->send($mailable = new EmbedMultilineMailable());

    $html = html_entity_decode($mailable->render());

    expect($html)->toContain('Embed multiline content: <img');
    expect($html)->toContain('alt="multiline image"');
    expect($html)->toContain('src="data:image/png;base64,');
    expect($html)->not->toContain('src="cid:');
});

test('embedded images are inlined when rendering mailable', function () {
    $html = app('mailer')->render('embed-image', [
        'image' => __DIR__.'/Fixtures/empty_image.jpg',
    ]);

    expect($html)->toContain('src="data:image/jpeg;base64,');
    expect($html)->not->toContain('src="cid:');
});

test('message as public property may be defined as view data', function () {
    Mail::to('test@mail.com')->send($mailable = new MessageAsPublicPropertyMailable());

    $mailable
        ->assertSeeInText('My message is: My message.')
        ->assertSeeInHtml('My message is: My message.');

    $email = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage()->toString();

    expect($email)->toContain('My message is: My message.');
});

test('message as with named parameter may be defined as view data', function () {
    Mail::to('test@mail.com')->send($mailable = new MessageAsWithNamedParameterMailable());

    $mailable
        ->assertSeeInText('My message is: My message.')
        ->assertSeeInHtml('My message is: My message.');

    $email = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage()->toString();

    expect($email)->toContain('My message is: My message.');
});

test('theme', function () {
    Mail::to('test@mail.com')->send(new BasicMailable());
    expect(app(Markdown::class)->getTheme())->toBe('default');

    Mail::to('test@mail.com')->send(new BasicMailableWithTheme());
    expect(app(Markdown::class)->getTheme())->toBe('taylor');

    Mail::to('test@mail.com')->send(new BasicMailable());
    expect(app(Markdown::class)->getTheme())->toBe('default');
});

test('embedded image content id consistency across mailer failover clones', function () {
    Mail::to('test@mail.com')->send($mailable = new EmbedImageMailable);

    /** @var \Symfony\Component\Mime\Email $originalEmail */
    $originalEmail = app('mailer')->getSymfonyTransport()->messages()[0]->getOriginalMessage();
    $expectedContentId = $originalEmail->getAttachments()[0]->getContentId();

    // Simulate failover mailer scenario where email is cloned for retry.
    // After shallow clone, the CID in HTML and attachment Content-ID header should remain consistent.
    $firstClonedEmail = quoted_printable_decode((clone $originalEmail)->toString());
    [$htmlCid, $attachmentContentId] = extractContentIdsFromEmail($firstClonedEmail);

    expect($attachmentContentId)->toEqual($htmlCid);
    expect($htmlCid)->toEqual($expectedContentId);

    // Verify consistency is maintained across multiple clone operations (e.g., multiple retries).
    $secondClonedEmail = quoted_printable_decode((clone $originalEmail)->toString());
    [$htmlCid, $attachmentContentId] = extractContentIdsFromEmail($secondClonedEmail);

    expect($attachmentContentId)->toEqual($htmlCid);
    expect($htmlCid)->toEqual($expectedContentId);
});

function extractContentIdsFromEmail(string $rawEmail): array
{
    // Extract CID from HTML <img src="cid:..."> tag.
    preg_match('/<img[^>]+src="cid:([^"]+)"/', $rawEmail, $htmlMatches);
    $htmlImageCid = $htmlMatches[1] ?? null;

    // Extract CID from MIME attachment Content-ID header.
    preg_match('/Content-ID:\s*<([^>]+)>/', $rawEmail, $headerMatches);
    $attachmentContentId = $headerMatches[1] ?? null;

    return [$htmlImageCid, $attachmentContentId];
}

class BasicMailable extends Mailable
{
    public function envelope()
    {
        return new Envelope(
            subject: 'My basic title',
        );
    }

    public function content()
    {
        return new Content(
            markdown: 'basic',
        );
    }
}

class BasicMailableWithTheme extends Mailable
{
    public $theme = 'taylor';

    public function envelope()
    {
        return new Envelope(
            subject: 'My basic title',
        );
    }

    public function content()
    {
        return new Content(
            markdown: 'basic',
        );
    }
}

class BasicMailableWithTextView extends Mailable
{
    public $textView = 'text';

    public function envelope()
    {
        return new Envelope(
            subject: 'My basic title',
        );
    }

    public function content()
    {
        return new Content(
            markdown: 'basic',
        );
    }
}

class EmbedMailable extends Mailable
{
    public function envelope()
    {
        return new Envelope(
            subject: 'My basic title',
        );
    }

    public function content()
    {
        return new Content(
            markdown: 'embed',
        );
    }
}

class EmbedMultilineMailable extends Mailable
{
    public function envelope()
    {
        return new Envelope(
            subject: 'My basic title',
        );
    }

    public function content()
    {
        return new Content(
            markdown: 'embed-multiline',
        );
    }
}

class EmbedDataMailable extends Mailable
{
    public function envelope()
    {
        return new Envelope(
            subject: 'My basic title',
        );
    }

    public function content()
    {
        return new Content(
            markdown: 'embed-data',
        );
    }
}

class EmbedImageMailable extends Mailable
{
    public function envelope()
    {
        return new Envelope(
            subject: 'My basic title',
        );
    }

    public function content()
    {
        return new Content(
            markdown: 'embed-image',
            with: [
                'image' => __DIR__.DIRECTORY_SEPARATOR.'Fixtures'.DIRECTORY_SEPARATOR.'empty_image.jpg',
            ]
        );
    }
}

class MessageAsPublicPropertyMailable extends Mailable
{
    public $message = 'My message';

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
}

class MessageAsWithNamedParameterMailable extends Mailable
{
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
            with: [
                'message' => 'My message',
            ]
        );
    }
}
