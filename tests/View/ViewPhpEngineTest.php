<?php

namespace Venusian\Serve\Tests\View;

use Voyager\Filesystem\Filesystem;
use Mariner\View\Engines\PhpEngine;

test('views may be properly rendered', function () {
    $engine = new PhpEngine(new Filesystem);
    expect($engine->get(__DIR__.'/Fixtures/basic.php'))->toBe('Hello World
');
});
