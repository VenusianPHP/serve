<?php

namespace Venusian\Serve\Tests\Mail;

use Mariner\Mail\Attachment;
use InvalidArgumentException;

test('from url with http scheme', function () {
    $attachment = Attachment::fromUrl('http://example.com/file.pdf');

    expect($attachment)->toBeInstanceOf(Attachment::class);
});

test('from url with https scheme', function () {
    $attachment = Attachment::fromUrl('https://example.com/file.pdf');

    expect($attachment)->toBeInstanceOf(Attachment::class);
});

test('from url throws for ftp scheme', function () {

    Attachment::fromUrl('ftp://example.com/file.pdf');
})->throws(InvalidArgumentException::class, 'Attachment URLs must use the http or https scheme.');

test('from url throws for file scheme', function () {

    Attachment::fromUrl('file:///var/www/file.pdf');
})->throws(InvalidArgumentException::class, 'Attachment URLs must use the http or https scheme.');

test('from url throws for mailto scheme', function () {

    Attachment::fromUrl('mailto:user@example.com');
})->throws(InvalidArgumentException::class, 'Attachment URLs must use the http or https scheme.');

test('from url throws for invalid url', function () {

    Attachment::fromUrl('not-a-url');
})->throws(InvalidArgumentException::class, 'Attachment URLs must use the http or https scheme.');

test('from url throws for empty string', function () {

    Attachment::fromUrl('');
})->throws(InvalidArgumentException::class, 'Attachment URLs must use the http or https scheme.');

test('as set filename', function () {
    $attachment = Attachment::fromPath('/path/to/file.pdf')
        ->as('renamed.pdf');

    expect($attachment->as)->toBe('renamed.pdf');
});

test('with mime sets mime type', function () {
    $attachment = Attachment::fromPath('/path/to/file.pdf')
        ->withMime('application/pdf');

    expect($attachment->mime)->toBe('application/pdf');
});

test('fluent chaining', function () {
    $attachment = Attachment::fromPath('/path/to/file.jpg')
        ->as('photo.jpg')
        ->withMime('image/jpeg');

    expect($attachment->as)->toBe('photo.jpg');
    expect($attachment->mime)->toBe('image/jpeg');
});

test('is equivalent with same path', function () {
    $a = Attachment::fromPath('/path/to/file.pdf')->as('file.pdf');
    $b = Attachment::fromPath('/path/to/file.pdf')->as('file.pdf');

    expect($a->isEquivalent($b))->toBeTrue();
});

test('is equivalent with different paths', function () {
    $a = Attachment::fromPath('/path/to/a.pdf');
    $b = Attachment::fromPath('/path/to/b.pdf');

    expect($a->isEquivalent($b))->toBeFalse();
});

test('from data creates attachment', function () {
    $attachment = Attachment::fromData(fn () => 'file content', 'report.txt');

    expect($attachment)->toBeInstanceOf(Attachment::class);
    expect($attachment->as)->toBe('report.txt');
});
