<?php

namespace Mariner\Core\Console;

use Exception;
use Mariner\Contracts\Http\MaintenanceMode;
use Mariner\Http\Events\MaintenanceModeDisabled;
use Symfony\Component\Console\Attribute\AsCommand;
use Voyager\Console\Command;

#[AsCommand(name: 'up')]
class UpCommand extends Command
{
    protected ?string $signature = 'up';

    protected string $description = 'Bring the application out of maintenance mode';

    public function handle(): int
    {
        try {
            $maintenance = $this->venusian->make(MaintenanceMode::class);

            if (! $maintenance->active()) {
                $this->components->info('Application is already up.');

                return self::SUCCESS;
            }

            $maintenance->deactivate();

            if (is_file($file = storage_path('framework/maintenance.php'))) {
                unlink($file);
            }

            $this->venusian->make('signals')->dispatch(new MaintenanceModeDisabled);

            $this->components->info('Application is now live.');
        } catch (Exception $e) {
            report($e);

            $this->components->error(sprintf('Failed to disable maintenance mode: %s.', $e->getMessage()));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }
}
