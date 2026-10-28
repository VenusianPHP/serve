<?php

namespace Mariner\Http\Maintenance;

use Mariner\Contracts\Http\MaintenanceMode;

/**
 * Maintenance state held in memory, for tests.
 */
class ArrayMaintenanceMode implements MaintenanceMode
{
    protected bool $maintenanceModeEnabled = false;

    protected array $data = [];

    public function activate(array $payload): void
    {
        $this->maintenanceModeEnabled = true;
        $this->data = $payload;
    }

    public function deactivate(): void
    {
        $this->maintenanceModeEnabled = false;
        $this->data = [];
    }

    public function active(): bool
    {
        return $this->maintenanceModeEnabled;
    }

    public function data(): array
    {
        return $this->data;
    }
}
