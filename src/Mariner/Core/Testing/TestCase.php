<?php

namespace Mariner\Core\Testing;

use Mariner\Core\Testing\Concerns\InteractsWithAuthentication;
use Mariner\Core\Testing\Concerns\InteractsWithContainer;
use Mariner\Core\Testing\Concerns\InteractsWithExceptionHandling;
use Mariner\Core\Testing\Concerns\InteractsWithSession;
use Mariner\Core\Testing\Concerns\InteractsWithViews;
use Mariner\Core\Testing\Concerns\MakesHttpRequests;
use Mariner\NutsAndBolts\Facades\Facade;
use Mockery;
use PHPUnit\Framework\TestCase as BaseTestCase;
use Voyager\Console\Testing\InteractsWithConsole;
use Voyager\Core\Bootstrap\HandleExceptions;
use Voyager\Core\RenderedInstance;
use Voyager\Vessel\ControlPanel;

/**
 * A test that boots the app and drives it through the HTTP kernel, as Laravel's feature tests do.
 *
 * Each trait the test uses may define setUp{TraitName}() and tearDown{TraitName}(), run around every test.
 */
abstract class TestCase extends BaseTestCase
{
    use InteractsWithAuthentication,
        InteractsWithConsole,
        InteractsWithContainer,
        InteractsWithExceptionHandling,
        InteractsWithSession,
        InteractsWithViews,
        MakesHttpRequests;

    /**
     * The app under test.
     *
     * @var \Voyager\Core\RenderedInstance|null
     */
    protected $app;

    /**
     * The tearDown{TraitName}() methods to run, in order.
     *
     * @var list<string>
     */
    private array $traitTearDowns = [];

    /**
     * Boot the app for one test.
     */
    abstract public function createApplication(): RenderedInstance;

    protected function setUp(): void
    {
        parent::setUp();

        $this->app = $this->createApplication();

        foreach (class_uses_recursive(static::class) as $trait) {
            $name = class_basename($trait);

            if (method_exists($this, $method = 'setUp'.$name)) {
                $this->{$method}();
            }

            if (method_exists($this, $method = 'tearDown'.$name)) {
                $this->traitTearDowns[] = $method;
            }
        }
    }

    protected function tearDown(): void
    {
        foreach (array_reverse($this->traitTearDowns) as $method) {
            $this->{$method}();
        }

        $this->traitTearDowns = [];

        if (class_exists(Mockery::class) && ($container = Mockery::getContainer())) {
            $this->addToAssertionCount($container->mockery_getExpectationCount());
            Mockery::close();
        }

        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        HandleExceptions::flushState($this);
        ControlPanel::setInstance(null);
        $this->app = null;

        parent::tearDown();
    }
}
