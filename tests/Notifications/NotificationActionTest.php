<?php

namespace Venusian\Serve\Tests\Notifications;

use Mariner\Notifications\Action;

test('action is created properly', function () {
    $action = new Action('Text', 'url');

    expect($action->text)->toBe('Text');
    expect($action->url)->toBe('url');
});
