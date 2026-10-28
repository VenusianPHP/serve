<?php

namespace Venusian\Serve\Tests\Mail;

use Mariner\Contracts\Mail\Attachable;
use Voyager\Filesystem\Testing\File;
use Mariner\Mail\Attachment;
use Mariner\Mail\Mailable;

test('it can have macro constructors', function () {
    Attachment::macro('fromInvoice', function ($name) {
        return Attachment::fromData(fn () => 'pdf content', $name);
    });
    $mailable = new Mailable;

    $mailable->attach(new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromInvoice('foo')
                ->as('bar')
                ->withMime('image/jpeg');
        }
    });

    expect($mailable->rawAttachments[0])->toBe([
        'data' => 'pdf content',
        'name' => 'bar',
        'options' => [
            'mime' => 'image/jpeg',
        ],
    ]);
});

test('it can utilise existing apis on non mail based resources with path', function () {
    Attachment::macro('size', function () {
        return 99;
    });
    $notification = new class()
    {
        public $pathArgs;

        public function withPathAttachment()
        {
            $this->pathArgs = func_get_args();
        }
    };
    $attachable = new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromPath('foo.jpg')
                ->as('bar')
                ->withMime('text/css');
        }
    };

    $attachable->toMailAttachment()->attachWith(
        fn ($path, $attachment) => $notification->withPathAttachment($path, $attachment->as, $attachment->mime, $attachment->size()),
        fn () => null
    );

    expect($notification->pathArgs)->toBe([
        'foo.jpg',
        'bar',
        'text/css',
        99,
    ]);
});

test('it can utilise existing apis on non mail based resources with args', function () {
    Attachment::macro('size', function () {
        return 99;
    });
    $notification = new class()
    {
        public $pathArgs;

        public $dataArgs;

        public function withDataAttachment()
        {
            $this->dataArgs = func_get_args();
        }
    };
    $attachable = new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromData(fn () => 'expected attachment body', 'bar')
                ->withMime('text/css');
        }
    };

    $attachable->toMailAttachment()->attachWith(
        fn () => null,
        fn ($data, $attachment) => $notification->withDataAttachment($data(), $attachment->as, $attachment->mime, $attachment->size()),
    );

    expect($notification->dataArgs)->toBe([
        'expected attachment body',
        'bar',
        'text/css',
        99,
    ]);
});

test('from url method', function () {
    $mailable = new class extends Mailable
    {
        public function build()
        {
            $this->attach(new class implements Attachable
            {
                public function toMailAttachment()
                {
                    return Attachment::fromUrl('https://example.com/file.pdf')
                        ->as('example.pdf')
                        ->withMime('application/pdf');
                }
            });
        }
    };

    $mailable->build();

    expect($mailable->attachments[0])->toBe([
        'file' => 'https://example.com/file.pdf',
        'options' => [
            'as' => 'example.pdf',
            'mime' => 'application/pdf',
        ],
    ]);
});

test('from uploaded file method', function () {
    $mailable = new class extends Mailable
    {
        public function build()
        {
            $this->attach(new class implements Attachable
            {
                public function toMailAttachment()
                {
                    return Attachment::fromUploadedFile(
                        File::createWithContent('example.pdf', 'content')
                            ->mimeType('application/pdf')
                    );
                }
            });
        }
    };

    $mailable->build();

    expect($mailable->rawAttachments[0])->toBe([
        'data' => 'content',
        'name' => 'example.pdf',
        'options' => [
            'mime' => 'application/pdf',
        ],
    ]);
});
