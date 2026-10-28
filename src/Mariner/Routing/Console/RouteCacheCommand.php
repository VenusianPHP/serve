<?php

namespace Mariner\Routing\Console;

use Mariner\NutsAndBolts\Facades\Facade;
use Mariner\Routing\RouteCache;
use Mariner\Routing\RouteCollection;
use Symfony\Component\Console\Attribute\AsCommand;
use Voyager\Console\Command;
use Voyager\Contracts\Console\Kernel as ConsoleKernel;
use Voyager\Filesystem\Filesystem;
use Voyager\Vessel\ControlPanel;

#[AsCommand(name: 'route:cache')]
class RouteCacheCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected ?string $signature = 'route:cache';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Create a route cache file for faster route registration';

    public function __construct(protected Filesystem $files)
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(RouteCache $cache): int
    {
        $this->callSilent('route:clear');

        $routes = $this->getFreshApplicationRoutes();

        if (count($routes) === 0) {
            $this->components->error("Your application doesn't have any routes.");

            return self::FAILURE;
        }

        foreach ($routes as $route) {
            $route->prepareForSerialization();
        }

        $this->files->put($cache->path(), $this->buildRouteCacheFile($routes));

        $this->components->info('Routes cached successfully.');

        return self::SUCCESS;
    }

    /**
     * Boot a fresh copy of the application and get its routes.
     *
     * The running app already loaded its routes (possibly from an old cache), so a second boot
     * registers them from the route files for real, the way config:cache reads config.
     */
    protected function getFreshApplicationRoutes(): RouteCollection
    {
        $app = require $this->venusian->make('path.bootstrap').DIRECTORY_SEPARATOR.'app.php';

        $app->make(ConsoleKernel::class)->bootstrap();

        $routes = tap($app['router']->getRoutes(), function (RouteCollection $routes) {
            $routes->refreshNameLookups();
            $routes->refreshActionLookups();
        });

        // Booting the copy made it the global container and the facade root; hand both back.
        ControlPanel::setInstance($this->venusian);
        Facade::setFacadeApplication($this->venusian);

        return $routes;
    }

    protected function buildRouteCacheFile(RouteCollection $routes): string
    {
        $stub = $this->files->get(__DIR__.'/stubs/routes.stub');

        return str_replace('{{routes}}', var_export($routes->compile(), true), $stub);
    }
}
