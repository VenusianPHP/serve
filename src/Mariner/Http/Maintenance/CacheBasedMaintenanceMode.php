<?php

namespace Mariner\Http\Maintenance;

use Mariner\Contracts\Http\MaintenanceMode;
use Voyager\Contracts\Cache\Factory;
use Voyager\Contracts\Cache\Repository;

/**
 * Maintenance state in a cache store, so every machine sharing the store goes down together.
 */
class CacheBasedMaintenanceMode implements MaintenanceMode
{
    public function __construct(
        protected Factory $cache,
        protected string $store,
        protected string $key,
    ) {}

    public function activate(array $payload): void
    {
        $this->getStore()->put($this->key, $payload);
    }

    public function deactivate(): void
    {
        $this->getStore()->forget($this->key);
    }

    public function active(): bool
    {
        return $this->getStore()->has($this->key);
    }

    public function data(): array
    {
        return $this->getStore()->get($this->key);
    }

    protected function getStore(): Repository
    {
        return $this->cache->store($this->store);
    }
}
