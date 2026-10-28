<?php

namespace Venusian\Serve\Tests\Fixtures;

use Mariner\NutsAndBolts\Concerns\LoadsWebResources;
use Voyager\NutsAndBolts\ServiceProvider;

/** Loads the fixture app's routes the way an application's own provider would. */
class RoutesServiceProvider extends ServiceProvider
{
    use LoadsWebResources;

    public function boot(): void
    {
        $this->loadRoutesFrom(__DIR__.'/app/routes/web.php');
    }
}
