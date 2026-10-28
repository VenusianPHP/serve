<?php

namespace Mariner\Core\Console;

use Carbon\Carbon;
use Exception;
use Mariner\Contracts\Http\MaintenanceMode;
use Mariner\Http\Events\MaintenanceModeEnabled;
use Mariner\Http\Middleware\PreventRequestsDuringMaintenance;
use Symfony\Component\Console\Attribute\AsCommand;
use Throwable;
use Voyager\Console\Command;
use Voyager\NutsAndBolts\Str;

#[AsCommand(name: 'down')]
class DownCommand extends Command
{
    protected ?string $signature = 'down {--redirect= : The path that users should be redirected to}
                                 {--render= : The view that should be prerendered for display during maintenance mode}
                                 {--retry= : The number of seconds or the datetime after which the request may be retried}
                                 {--refresh= : The number of seconds after which the browser may refresh}
                                 {--secret= : The secret phrase that may be used to bypass maintenance mode}
                                 {--with-secret : Generate a random secret phrase that may be used to bypass maintenance mode}
                                 {--status=503 : The status code that should be used when returning the maintenance mode response}';

    protected string $description = 'Put the application into maintenance / demo mode';

    public function handle(): int
    {
        try {
            $maintenance = $this->venusian->make(MaintenanceMode::class);
            $wasAlreadyDown = $maintenance->active();
            $payload = $this->getDownFilePayload();

            $maintenance->activate($payload);

            // The front controller's pre-boot check, for servers that start PHP per request.
            file_put_contents(
                storage_path('framework/maintenance.php'),
                file_get_contents(__DIR__.'/stubs/maintenance-mode.stub')
            );

            $this->venusian->make('signals')->dispatch(new MaintenanceModeEnabled);

            $this->components->info($wasAlreadyDown
                ? 'Maintenance mode options updated.'
                : 'Application is now in maintenance mode.');

            if ($payload['secret'] !== null) {
                $this->components->info('You may bypass maintenance mode via ['.config('webapp.url')."/{$payload['secret']}].");
            }
        } catch (Exception $e) {
            report($e);

            $this->components->error(sprintf('Failed to enter maintenance mode: %s.', $e->getMessage()));

            return self::FAILURE;
        }

        return self::SUCCESS;
    }

    /**
     * Get the payload to be placed in the "down" file.
     */
    protected function getDownFilePayload(): array
    {
        return [
            'except' => $this->excludedPaths(),
            'redirect' => $this->redirectPath(),
            'retry' => $this->getRetryTime(),
            'refresh' => $this->option('refresh'),
            'secret' => $this->getSecret(),
            'status' => (int) ($this->option('status') ?? 503),
            'template' => $this->option('render') ? $this->prerenderView() : null,
        ];
    }

    /**
     * The paths the maintenance middleware lets through.
     */
    protected function excludedPaths(): array
    {
        try {
            return $this->venusian->make(PreventRequestsDuringMaintenance::class)->getExcludedPaths();
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Get the path that users should be redirected to.
     */
    protected function redirectPath(): ?string
    {
        if ($this->option('redirect') && $this->option('redirect') !== '/') {
            return '/'.trim($this->option('redirect'), '/');
        }

        return $this->option('redirect');
    }

    /**
     * Render the view that should be shown during maintenance, once, now.
     */
    protected function prerenderView(): string
    {
        return view($this->option('render'), [
            'retryAfter' => $this->option('retry'),
        ])->render();
    }

    /**
     * Get the number of seconds or date / time the client should wait before retrying their request.
     */
    protected function getRetryTime(): int|string|null
    {
        $retry = $this->option('retry');

        if (is_numeric($retry) && $retry > 0) {
            return (int) $retry;
        }

        if (is_string($retry) && ! empty($retry)) {
            try {
                return Carbon::parse($retry)->toRfc7231String();
            } catch (Exception) {
                return null;
            }
        }

        return null;
    }

    /**
     * Get the secret phrase that may be used to bypass maintenance mode.
     */
    protected function getSecret(): ?string
    {
        return match (true) {
            ! is_null($this->option('secret')) => $this->option('secret'),
            $this->option('with-secret') => Str::random(),
            default => null,
        };
    }
}
