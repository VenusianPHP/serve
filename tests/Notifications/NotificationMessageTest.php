<?php

namespace Venusian\Serve\Tests\Notifications;

use Mariner\Notifications\Messages\SimpleMessage as Message;

test('level can be retrieved', function () {
    $message = new Message;
    expect($message->level)->toBe('info');

    $message = new Message;
    $message->level('error');
    expect($message->level)->toBe('error');
});

test('message formats multi line text', function () {
    $message = new Message;
    $message->with('
            This is a
            single line of text.
        ');

    expect($message->introLines[0])->toBe('This is a single line of text.');

    $message = new Message;
    $message->with([
        'This is a',
        'single line of text.',
    ]);

    expect($message->introLines[0])->toBe('This is a single line of text.');
});
