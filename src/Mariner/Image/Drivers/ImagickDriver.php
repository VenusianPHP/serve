<?php

namespace Mariner\Image\Drivers;

use Intervention\Image\Drivers\Imagick\Driver as InterventionImagickDriver;
use Intervention\Image\ImageManager;

class ImagickDriver extends InterventionDriver
{
    /**
     * Create the underlying Intervention image manager.
     */
    protected function createManager(): ImageManager
    {
        return new ImageManager(InterventionImagickDriver::class);
    }
}
