<?php

namespace Mariner\Http\Maintenance;

use Mariner\Contracts\Http\MaintenanceMode;

/**
 * Maintenance state in storage/framework/down, read on every check.
 */
class FileBasedMaintenanceMode implements MaintenanceMode
{
    public function activate(array $payload): void
    {
        file_put_contents($this->path(), json_encode($payload, JSON_PRETTY_PRINT));
    }

    public function deactivate(): void
    {
        if ($this->active()) {
            unlink($this->path());
        }
    }

    public function active(): bool
    {
        // Checked on every request, so a long-running server notices `down`/`up` from another process.
        // PHP 8.4 does not serve a stale answer here; the clear keeps that true whatever the stat cache does.
        clearstatcache(true, $path = $this->path());

        return file_exists($path);
    }

    public function data(): array
    {
        return json_decode(file_get_contents($this->path()), true);
    }

    protected function path(): string
    {
        return storage_path('framework/down');
    }
}
