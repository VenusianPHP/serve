<?php

use Mariner\NutsAndBolts\Concurrency\Offload;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mime\Email;
use Venusian\Serve\Tests\Fixtures\EmailEcho;
use Venusian\Serve\Tests\Fixtures\WebApp;

beforeEach(function () {
    $this->app = WebApp::boot();
    $this->file = tempnam(sys_get_temp_dir(), 'mariner-mail');
    file_put_contents($this->file, 'from a file');
});

afterEach(function () {
    @unlink($this->file);
    WebApp::tearDown($this);
});

dataset('pools', array_merge(['process'], extension_loaded('parallel') ? ['thread'] : []));

it('carries an email with every kind of attachment to a worker intact', function (string $pool) {
    $stream = fopen('php://memory', 'r+');
    fwrite($stream, 'from a stream');
    rewind($stream);

    $email = (new Email)
        ->from('ada@example.test')->to('grace@example.test')->subject('Engines')
        ->text('plain body')->html('<p>html body</p>')
        ->attach('from memory', 'memory.txt', 'text/plain')
        ->attachFromPath($this->file, 'path.txt', 'text/plain')
        ->attach($stream, 'stream.txt', 'text/plain');

    $arrived = $this->app['event-loop']->await(Offload::submit($this->app, new EmailEcho($email, Envelope::create($email)), $pool));

    expect($arrived)->toBe([
        'subject' => 'Engines',
        'from' => 'ada@example.test',
        'to' => ['grace@example.test'],
        'text' => 'plain body',
        'html' => '<p>html body</p>',
        'attachments' => [
            ['memory.txt', 'text/plain', md5('from memory')],
            ['path.txt', 'text/plain', md5('from a file')],
            ['stream.txt', 'text/plain', md5('from a stream')],
        ],
        'raw' => true,
    ]);
})->with('pools');
