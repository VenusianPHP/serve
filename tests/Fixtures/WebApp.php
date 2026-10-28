<?php

namespace Venusian\Serve\Tests\Fixtures;

use Mariner\NutsAndBolts\Facades\Facade;
use PHPUnit\Framework\TestCase;
use Voyager\Contracts\Console\Kernel;
use Voyager\Core\Bootstrap\HandleExceptions;
use Voyager\Core\RenderedInstance;
use Voyager\Vessel\ControlPanel;

/**
 * A Venusian app booted from tests/Fixtures/app with Mariner registered, as an application
 * that installs venusian/serve would boot.
 */
final class WebApp
{
    public static function boot(): RenderedInstance
    {
        return self::bootFrom(__DIR__.'/app');
    }

    /** Boot the app whose root is $root: a copy of tests/Fixtures/app a test may write into. */
    public static function bootFrom(string $root): RenderedInstance
    {
        $app = require $root.'/bootstrap/app.php';
        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    /** Copy tests/Fixtures/app to a new temporary directory, returning its path. */
    public static function copy(): string
    {
        $root = sys_get_temp_dir().'/serve-app-'.bin2hex(random_bytes(6));

        (new \Voyager\Filesystem\Filesystem)->copyDirectory(__DIR__.'/app', $root);

        return $root;
    }

    /** Forgets the app, the facade roots, and hands PHPUnit back the error handlers the boot replaced. */
    public static function tearDown(TestCase $test): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        HandleExceptions::flushState($test);
        ControlPanel::setInstance(null);
    }
}
