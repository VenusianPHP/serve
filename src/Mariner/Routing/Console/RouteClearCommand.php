<?php

namespace Mariner\Routing\Console;

use Mariner\Routing\RouteCache;
use Symfony\Component\Console\Attribute\AsCommand;
use Voyager\Console\Command;

#[AsCommand(name: 'route:clear')]
class RouteClearCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected ?string $signature = 'route:clear';

    /**
     * The console command description.
     *
     * @var string
     */
    protected string $description = 'Remove the route cache file';

    /**
     * Execute the console command.
     */
    public function handle(RouteCache $cache): int
    {
        $cache->clear();

        $this->components->info('Route cache cleared successfully.');

        return self::SUCCESS;
    }
}
