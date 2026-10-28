<?php

namespace Venusian\Serve\Tests\Notifications;

use Voyager\Config\Repository as ConfigRepository;
use Voyager\Vessel\ControlPanel;
use Voyager\Contracts\Filesystem\Factory as FilesystemFactory;
use Mariner\Contracts\Mail\Attachable;
use Voyager\Filesystem\Filesystem;
use Voyager\Filesystem\FilesystemManager;
use Mariner\Mail\Attachment;
use Mariner\Notifications\Messages\MailMessage;
use Venusian\Serve\Tests\Fixtures\WebApp;

beforeEach(fn () => $this->filesystemRoot = null);

afterEach(function () {
    if ($this->filesystemRoot !== null) {
        deleteDirectory($this->filesystemRoot);
        $this->filesystemRoot = null;
        WebApp::tearDown($this);
    }
});

test('template', function () {
    $message = new MailMessage;

    expect($message->markdown)->toBe('notifications::email');

    $message->template('notifications::foo');

    expect($message->markdown)->toBe('notifications::foo');
});

test('html and plain view', function () {
    $message = new MailMessage;

    expect($message->view)->toBeNull();
    expect($message->viewData)->toBe([]);

    $message->view(['notifications::foo', 'notifications::bar'], [
        'foo' => 'bar',
    ]);

    expect($message->view[0])->toBe('notifications::foo');
    expect($message->view[1])->toBe('notifications::bar');
    expect($message->viewData)->toBe(['foo' => 'bar']);
});

test('html view', function () {
    $message = new MailMessage;

    expect($message->view)->toBeNull();
    expect($message->viewData)->toBe([]);

    $message->view('notifications::foo', [
        'foo' => 'bar',
    ]);

    expect($message->view)->toBe('notifications::foo');
    expect($message->viewData)->toBe(['foo' => 'bar']);
});

test('plain view', function () {
    $message = new MailMessage;

    expect($message->view)->toBeNull();
    expect($message->viewData)->toBe([]);

    $message->view([null, 'notifications::foo'], [
        'foo' => 'bar',
    ]);

    expect($message->view[1])->toBe('notifications::foo');
    expect($message->viewData)->toBe(['foo' => 'bar']);
});

test('cc is set correctly', function () {
    $message = new MailMessage;
    $message->cc('test@example.com');

    expect($message->cc)->toBe([['test@example.com', null]]);

    $message = new MailMessage;
    $message->cc('test@example.com')
        ->cc('test@example.com', 'Test');

    expect($message->cc)->toBe([['test@example.com', null], ['test@example.com', 'Test']]);

    $message = new MailMessage;
    $message->cc(['test@example.com', 'Test' => 'test@example.com']);

    expect($message->cc)->toBe([['test@example.com', null], ['test@example.com', 'Test']]);

    $message = new MailMessage;
    $message->cc('test@example.com', 'Test')
        ->cc(['test@example.com', 'test2@example.com']);

    expect($message->cc)->toBe([
        ['test@example.com', 'Test'],
        ['test@example.com', null],
        ['test2@example.com', null],
    ]);
});

test('bcc is set correctly', function () {
    $message = new MailMessage;
    $message->bcc('test@example.com');

    expect($message->bcc)->toBe([['test@example.com', null]]);

    $message = new MailMessage;
    $message->bcc('test@example.com')
        ->bcc('test@example.com', 'Test');

    expect($message->bcc)->toBe([['test@example.com', null], ['test@example.com', 'Test']]);

    $message = new MailMessage;
    $message->bcc(['test@example.com', 'Test' => 'test@example.com']);

    expect($message->bcc)->toBe([['test@example.com', null], ['test@example.com', 'Test']]);

    $message = new MailMessage;
    $message->bcc('test@example.com', 'Test')
        ->bcc(['test@example.com', 'test2@example.com']);

    expect($message->bcc)->toBe([
        ['test@example.com', 'Test'],
        ['test@example.com', null],
        ['test2@example.com', null],
    ]);
});

test('reply to is set correctly', function () {
    $message = new MailMessage;
    $message->replyTo('test@example.com');

    expect($message->replyTo)->toBe([['test@example.com', null]]);

    $message = new MailMessage;
    $message->replyTo('test@example.com')
        ->replyTo('test@example.com', 'Test');

    expect($message->replyTo)->toBe([['test@example.com', null], ['test@example.com', 'Test']]);

    $message = new MailMessage;
    $message->replyTo(['test@example.com', 'Test' => 'test@example.com']);

    expect($message->replyTo)->toBe([['test@example.com', null], ['test@example.com', 'Test']]);

    $message = new MailMessage;
    $message->replyTo('test@example.com', 'Test')
        ->replyTo(['test@example.com', 'test2@example.com']);

    expect($message->replyTo)->toBe([
        ['test@example.com', 'Test'],
        ['test@example.com', null],
        ['test2@example.com', null],
    ]);
});

test('metadata is set correctly', function () {
    $message = new MailMessage;
    $message->metadata('origin', 'test-suite');
    $message->metadata('user_id', 1);

    expect($message->metadata)->toHaveKey('origin');
    expect($message->metadata['origin'])->toBe('test-suite');
    expect($message->metadata)->toHaveKey('user_id');
    expect($message->metadata['user_id'])->toBe(1);
});

test('tag is set correctly', function () {
    $message = new MailMessage;
    $message->tag('test');

    expect($message->tags)->toContain('test');
});

test('callback is set correctly', function () {
    $callback = function () {
        //
    };

    $message = new MailMessage;
    $message->withSymfonyMessage($callback);

    expect($message->callbacks)->toBe([$callback]);
});

test('when callback', function () {
    $callback = function (MailMessage $mailMessage, $condition) {
        expect($condition)->toBeTrue();

        $mailMessage->cc('cc@example.com');
    };

    $message = new MailMessage;
    $message->when(true, $callback);
    expect($message->cc)->toBe([['cc@example.com', null]]);

    $message = new MailMessage;
    $message->when(false, $callback);
    expect($message->cc)->toBe([]);
});

test('when callback with return', function () {
    $callback = function (MailMessage $mailMessage, $condition) {
        expect($condition)->toBeTrue();

        return $mailMessage->cc('cc@example.com');
    };

    $message = new MailMessage;
    $message->when(true, $callback)->bcc('bcc@example.com');
    expect($message->cc)->toBe([['cc@example.com', null]]);
    expect($message->bcc)->toBe([['bcc@example.com', null]]);

    $message = new MailMessage;
    $message->when(false, $callback)->bcc('bcc@example.com');
    expect($message->cc)->toBe([]);
    expect($message->bcc)->toBe([['bcc@example.com', null]]);
});

test('when callback with default', function () {
    $callback = function (MailMessage $mailMessage, $condition) {
        expect($condition)->toBe('truthy');

        $mailMessage->cc('truthy@example.com');
    };

    $default = function (MailMessage $mailMessage, $condition) {
        expect($condition)->toEqual(0);

        $mailMessage->cc('zero@example.com');
    };

    $message = new MailMessage;
    $message->when('truthy', $callback, $default);
    expect($message->cc)->toBe([['truthy@example.com', null]]);

    $message = new MailMessage;
    $message->when(0, $callback, $default);
    expect($message->cc)->toBe([['zero@example.com', null]]);
});

test('unless callback', function () {
    $callback = function (MailMessage $mailMessage, $condition) {
        expect($condition)->toBeFalse();

        $mailMessage->cc('test@example.com');
    };

    $message = new MailMessage;
    $message->unless(false, $callback);
    expect($message->cc)->toBe([['test@example.com', null]]);

    $message = new MailMessage;
    $message->unless(true, $callback);
    expect($message->cc)->toBe([]);
});

test('unless callback with return', function () {
    $callback = function (MailMessage $mailMessage, $condition) {
        expect($condition)->toBeFalse();

        return $mailMessage->cc('cc@example.com');
    };

    $message = new MailMessage;
    $message->unless(false, $callback)->bcc('bcc@example.com');
    expect($message->cc)->toBe([['cc@example.com', null]]);
    expect($message->bcc)->toBe([['bcc@example.com', null]]);

    $message = new MailMessage;
    $message->unless(true, $callback)->bcc('bcc@example.com');
    expect($message->cc)->toBe([]);
    expect($message->bcc)->toBe([['bcc@example.com', null]]);
});

test('unless callback with default', function () {
    $callback = function (MailMessage $mailMessage, $condition) {
        expect($condition)->toEqual(0);

        $mailMessage->cc('zero@example.com');
    };

    $default = function (MailMessage $mailMessage, $condition) {
        expect($condition)->toBe('truthy');

        $mailMessage->cc('truthy@example.com');
    };

    $message = new MailMessage;
    $message->unless(0, $callback, $default);
    expect($message->cc)->toBe([['zero@example.com', null]]);

    $message = new MailMessage;
    $message->unless('truthy', $callback, $default);
    expect($message->cc)->toBe([['truthy@example.com', null]]);
});

test('it attaches files from storage', function () {
    $this->filesystemRoot = bootstrapFilesystem();

    file_put_contents($this->filesystemRoot.'/invoices/1.pdf', 'pdf content');

    $message = new MailMessage;
    $message->attachFromStorage('invoices/1.pdf', 'invoice.pdf');

    expect($message->rawAttachments)->toHaveCount(1);
    expect($message->rawAttachments[0]['name'])->toBe('invoice.pdf');
    expect($message->rawAttachments[0]['data'])->toBe('pdf content');
});

test('it attaches files from storage disk', function () {
    $this->filesystemRoot = bootstrapFilesystem();

    file_put_contents($this->filesystemRoot.'/reports/report.txt', 'report content');

    $message = new MailMessage;
    $message->attachFromStorageDisk('s3', 'reports/report.txt', 'monthly-report.txt', [
        'mime' => 'text/plain',
    ]);

    expect($message->rawAttachments)->toHaveCount(1);
    expect($message->rawAttachments[0]['name'])->toBe('monthly-report.txt');
    expect($message->rawAttachments[0]['data'])->toBe('report content');
    expect($message->rawAttachments[0]['options']['mime'])->toBe('text/plain');
});

test('it attaches files via attachable contract from path', function () {
    $message = new MailMessage;

    $message->attach(new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromPath('/foo.jpg')->as('bar')->withMime('image/png');
        }
    });

    expect($message->attachments[0])->toBe([
        'file' => '/foo.jpg',
        'options' => [
            'as' => 'bar',
            'mime' => 'image/png',
        ],
    ]);
});

test('it attaches files via attachable contract from data', function () {
    $mailMessage = new MailMessage();

    $mailMessage->attach(new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromData(fn () => 'bar', 'foo.jpg')->withMime('image/png');
        }
    });

    expect($mailMessage->rawAttachments[0])->toBe([
        'data' => 'bar',
        'name' => 'foo.jpg',
        'options' => [
            'mime' => 'image/png',
        ],
    ]);
});

test('it attaches many files', function () {
    $mailMessage = new MailMessage();
    $attachable = new class() implements Attachable
    {
        public function toMailAttachment()
        {
            return Attachment::fromData(fn () => 'bar', 'foo.jpg')->withMime('image/png');
        }
    };

    $mailMessage->attachMany([
        $attachable,
        '/path/to/forge.svg',
        '/path/to/vapor.svg' => [
            'as' => 'Logo.svg',
            'mime' => 'image/svg+xml',
        ],
    ]);

    expect($mailMessage->rawAttachments)->toBe([
        [
            'data' => 'bar',
            'name' => 'foo.jpg',
            'options' => [
                'mime' => 'image/png',
            ],
        ],
    ]);

    expect($mailMessage->attachments)->toBe([
        [
            'file' => '/path/to/forge.svg',
            'options' => [],
        ],
        [
            'file' => '/path/to/vapor.svg',
            'options' => [
                'as' => 'Logo.svg',
                'mime' => 'image/svg+xml',
            ],
        ],
    ]);
});

function bootstrapFilesystem(): string
{
    $filesystemRoot = sys_get_temp_dir().'/mariner-notification-mail-message-'.uniqid();

    mkdir($filesystemRoot.'/invoices', 0777, true);
    mkdir($filesystemRoot.'/reports', 0777, true);

    $app = WebApp::boot();
    $app['config']->set('filesystems.default', 'local');
    $app['config']->set('filesystems.disks.local', ['driver' => 'local', 'root' => $filesystemRoot]);
    $app['config']->set('filesystems.disks.s3', ['driver' => 'local', 'root' => $filesystemRoot]);

    return $filesystemRoot;
}

function deleteDirectory(string $directory): void
{
    if (! is_dir($directory)) {
        return;
    }

    foreach (scandir($directory) as $item) {
        if ($item === '.' || $item === '..') {
            continue;
        }

        $path = $directory.'/'.$item;

        is_dir($path) ? deleteDirectory($path) : unlink($path);
    }

    rmdir($directory);
}
