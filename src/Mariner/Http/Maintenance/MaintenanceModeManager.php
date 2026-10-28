<?php

namespace Mariner\Http\Maintenance;

use Voyager\NutsAndBolts\Manager;

class MaintenanceModeManager extends Manager
{
    protected function createFileDriver(): FileBasedMaintenanceMode
    {
        return new FileBasedMaintenanceMode;
    }

    protected function createArrayDriver(): ArrayMaintenanceMode
    {
        return new ArrayMaintenanceMode;
    }

    protected function createCacheDriver(): CacheBasedMaintenanceMode
    {
        return new CacheBasedMaintenanceMode(
            $this->vessel->make('cache'),
            $this->config->get('webapp.maintenance.store') ?: $this->config->get('cache.default'),
            'venusian:down'
        );
    }

    public function getDefaultDriver(): ?string
    {
        return $this->config->get('webapp.maintenance.driver', 'file');
    }
}
