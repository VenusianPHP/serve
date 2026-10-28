<?php

use Symfony\Component\Console\Input\ArgvInput;
use Venusian\Serve\Tests\Fixtures\ServingSketch;
use Venusian\Serve\Tests\Fixtures\WebApp;
use Voyager\Filesystem\Filesystem;

beforeEach(function () {
    $this->root = WebApp::copy();

    // The sketch is registered as an app built with venusian/build registers its own.
    $bootstrap = file_get_contents($this->root.'/bootstrap/app.php');
    file_put_contents($this->root.'/bootstrap/app.php', str_replace(
        "VenusianVoyager::setup(base_path: dirname(__DIR__))->create();",
        "VenusianVoyager::setup(base_path: dirname(__DIR__))->withSketches([\\Venusian\\Serve\\Tests\\Fixtures\\ServingSketch::class])->create();",
        $bootstrap,
    ));

    // The app's own routes, which the sketch's server serves.
    file_put_contents($this->root.'/routes/sketch.php', <<<'PHP'
        <?php

        use Mariner\NutsAndBolts\Facades\Route;
        use Venusian\Serve\Tests\Fixtures\LoopPause;

        Route::get('/sketch/state', fn () => app('sketch.state')['greeting']);

        Route::get('/sketch/slow', function () {
            posix_kill(getmypid(), SIGTERM);   // Ctrl-C arrives while this request is in flight
            LoopPause::yield();
            LoopPause::yield();

            return 'finished';
        });
        PHP);
    file_put_contents($this->root.'/config/routing.php', "<?php\n\nreturn ['web' => null, 'api' => '{$this->root}/routes/sketch.php', 'api_prefix' => ''];\n");

    ServingSketch::$results = $this->root.'/results';
});

afterEach(fn () => (new Filesystem)->deleteDirectory($this->root));

/** Run the 'serving' sketch the way `php rocket serving` does. */
function runSketch($test, string $root, string $mode): int
{
    ServingSketch::$mode = $mode;
    $app = require $root.'/bootstrap/app.php';

    try {
        return $app->handleSketch(new ArgvInput(['rocket', 'serving']));
    } finally {
        \Voyager\Core\Bootstrap\HandleExceptions::flushState($test);
        \Mariner\NutsAndBolts\Facades\Facade::clearResolvedInstances();
        \Mariner\NutsAndBolts\Facades\Facade::setFacadeApplication(null);
        \Voyager\Vessel\ControlPanel::setInstance(null);
    }
}

it('serves the sketch\'s own app from inside its run: state the sketch set, ticking on, then drained', function () {
    $status = runSketch($this, $this->root, 'drain');
    $record = ServingSketch::$record;

    expect($status)->toBe(0)
        ->and(file_get_contents($this->root.'/results'))->toBe('hello from the sketch')
        ->and($record['ticks_after'])->toBeGreaterThan($record['ticks_before'])
        ->and($record['same_scoped'])->toBeTrue()
        ->and($record['shutdown'])->toBeTrue();
});

it('drains the server on Ctrl-C through the sketch\'s own stop handler, and the sketch still shuts down', function () {
    $status = runSketch($this, $this->root, 'signal');
    $record = ServingSketch::$record;

    expect($status)->toBe(0)
        ->and(file_get_contents($this->root.'/results'))->toBe('finished')
        ->and($record['same_scoped'])->toBeTrue()
        ->and($record['shutdown'])->toBeTrue();
})->skip(! function_exists('posix_kill'), 'needs posix to send itself SIGTERM');
