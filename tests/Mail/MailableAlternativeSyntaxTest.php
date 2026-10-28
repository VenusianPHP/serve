<?php

namespace Venusian\Serve\Tests\Mail;

use Mariner\Mail\Mailable;
use Mariner\Mail\Mailables\Address;
use Mariner\Mail\Mailables\Content;
use Mariner\Mail\Mailables\Envelope;
use ReflectionClass;

test('basic mailable inspection', function () {
    $mailable = new MailableWithAlternativeSyntax;

    expect($mailable->hasTo('taylor@laravel.com'))->toBeTrue();
    expect($mailable->hasCc('adam@laravel.com'))->toBeTrue();
    expect($mailable->hasBcc('tyler@laravel.com'))->toBeTrue();
    expect($mailable->hasTo('taylor@laravel.com', 'Taylor Otwell'))->toBeTrue();
    expect($mailable->hasTo('taylor@laravel.com', 'Wrong Name'))->toBeFalse();

    $mailable->to(new Address('abigail@laravel.com', 'Abigail Otwell'));
    expect($mailable->hasTo('taylor@laravel.com', 'Taylor Otwell'))->toBeTrue();

    expect($mailable->hasSubject('Test Subject'))->toBeTrue();
    expect($mailable->hasSubject('Wrong Subject'))->toBeFalse();
    expect($mailable->hasTag('tag-1'))->toBeTrue();
    expect($mailable->hasMetadata('test-meta', 'test-meta-value'))->toBeTrue();

    $reflection = new ReflectionClass($mailable);
    $method = $reflection->getMethod('prepareMailableForDelivery');
    $method->invoke($mailable);

    expect($mailable->view)->toBe('test-view');
    expect($mailable->viewData)->toEqual(['test-data-key' => 'test-data-value']);
    expect($mailable->to)->toHaveCount(2);
    expect($mailable->cc)->toHaveCount(1);
    expect($mailable->bcc)->toHaveCount(1);
});

test('envelopes can receive additional recipients', function () {
    $envelope = new Envelope(to: ['taylor@example.com']);
    $envelope->to(new Address('taylorotwell@example.com'));

    expect($envelope->to)->toHaveCount(2);
    expect($envelope->to[0]->address)->toBe('taylor@example.com');
    expect($envelope->to[1]->address)->toBe('taylorotwell@example.com');

    $envelope->to('abigailotwell@example.com', 'Abigail Otwell');
    expect($envelope->to[2]->address)->toBe('abigailotwell@example.com');
    expect($envelope->to[2]->name)->toBe('Abigail Otwell');

    $envelope->to('adam@example.com');
    expect($envelope->to[3]->address)->toBe('adam@example.com');
    expect($envelope->to[3]->name)->toBeNull();

    $envelope->to(['jeffrey@example.com', 'tyler@example.com']);
    expect($envelope->to[4]->address)->toBe('jeffrey@example.com');
    expect($envelope->to[5]->address)->toBe('tyler@example.com');

    $envelope->from('dries@example.com', 'Dries Vints');
    expect($envelope->from->address)->toBe('dries@example.com');
    expect($envelope->from->name)->toBe('Dries Vints');
});

class MailableWithAlternativeSyntax extends Mailable
{
    public function envelope()
    {
        return new Envelope(
            to: [new Address('taylor@laravel.com', 'Taylor Otwell')],
            cc: [new Address('adam@laravel.com', 'Adam Wathan')],
            bcc: [new Address('tyler@laravel.com', 'Tyler Blair')],
            subject: 'Test Subject',
            tags: ['tag-1', 'tag-2'],
            metadata: ['test-meta' => 'test-meta-value'],
        );
    }

    public function content()
    {
        return new Content(
            view: 'test-view',
            with: ['test-data-key' => 'test-data-value'],
        );
    }
}
