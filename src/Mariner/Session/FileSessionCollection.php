<?php

namespace Mariner\Session;

use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;
use Voyager\Filesystem\Filesystem;

/**
 * Collects expired file sessions. A directory scan can't be offloaded call by call, so a served
 * request hands the whole scan to a worker; elsewhere it runs in place.
 */
final readonly class FileSessionCollection implements ShouldPool
{
    public function __construct(
        private string $path,
        private int $lifetime,
    ) {}

    public function handle(): int
    {
        return (new FileSessionHandler(new Filesystem, $this->path, 0))->collect($this->lifetime);
    }
}
