<?php

namespace Venusian\Serve\Tests\Integration\Mail;

use Mariner\Mail\Attachment;
use Mariner\Mail\Mailable;
use Mariner\Notifications\Messages\MailMessage;
use Voyager\Filesystem\Filesystem;

// The local disk points at a directory of its own, so the files these tests write never reach the fixture app.
beforeEach(function () {
    $this->storageRoot = sys_get_temp_dir().'/serve-storage-'.bin2hex(random_bytes(6));
    $this->app['config']->set('filesystems.disks.local.root', $this->storageRoot);
});

afterEach(fn () => (new Filesystem)->deleteDirectory($this->storageRoot));

test('it can attach from storage', function () {
    app('filesystem')->disk('local')->put('/dir/foo.png', 'expected body contents');
    $mail = new MailMessage();
    $attachment = Attachment::fromStorageDisk('local', '/dir/foo.png')
        ->as('bar')
        ->withMime('text/css');

    $attachment->attachTo($mail);

    expect($mail->rawAttachments[0])->toBe([
        'data' => 'expected body contents',
        'name' => 'bar',
        'options' => [
            'mime' => 'text/css',
        ],
    ]);

    app('filesystem')->disk('local')->delete('/dir/foo.png');
});

test('it can attach from storage and fallback to storage name and mime', function () {
    app('filesystem')->disk()->put('/dir/foo.png', 'expected body contents');
    $mail = new MailMessage();
    $attachment = Attachment::fromStorageDisk('local', '/dir/foo.png');

    $attachment->attachTo($mail);

    expect($mail->rawAttachments[0])->toBe([
        'data' => 'expected body contents',
        'name' => 'foo.png',
        'options' => [
            // when using "prefer-lowest" the local filesystem driver will
            // not detect the mime type based on the extension and will
            // instead fallback to "text/plain".
            'mime' => class_exists(\League\Flysystem\Local\FallbackMimeTypeDetector::class)
                ? 'image/png'
                : 'text/plain',
        ],
    ]);

    app('filesystem')->disk('local')->delete('/dir/foo.png');
});

test('it can chain attach with mail message', function () {
    app('filesystem')->disk('local')->put('/dir/foo.png', 'expected body contents');
    $message = new MailMessage();

    $result = $message->attach(
        Attachment::fromStorageDisk('local', '/dir/foo.png')
    );

    expect($result)->toBe($message);
});

test('it can check for storage based attachments', function () {
    app('filesystem')->disk()->put('/dir/foo.png', 'expected body contents');
    $mailable = new Mailable();
    $mailable->attach(Attachment::fromStorage('/dir/foo.png'));

    expect($mailable->hasAttachment(Attachment::fromStorage('/dir/foo.png')))->toBeTrue();
});
