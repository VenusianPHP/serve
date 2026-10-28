<?php

namespace Mariner\Image;

use Throwable;
use Voyager\Contracts\IOPools\WorkerPools\ShouldPool;
use Voyager\Vessel\ControlPanel;

/**
 * Runs an image pipeline on a worker with the named driver. A failure comes back as its message,
 * so the caller can raise the ImageException a worker's exception class would not survive as.
 */
class ProcessImage implements ShouldPool
{
    public function __construct(
        public string $driver,
        public string $contents,
        public ImagePipeline $pipeline,
    ) {}

    public function handle(): mixed
    {
        try {
            return ['bytes' => (new ImageManager(ControlPanel::getInstance()))->driver($this->driver)->process($this->contents, $this->pipeline)];
        } catch (Throwable $e) {
            return ['error' => $e->getMessage()];
        }
    }
}
