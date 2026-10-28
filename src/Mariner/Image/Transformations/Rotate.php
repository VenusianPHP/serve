<?php

namespace Mariner\Image\Transformations;

use Mariner\Contracts\Image\Transformation;

class Rotate implements Transformation
{
    public function __construct(
        public readonly float $angle,
        public readonly ?string $background = null,
    ) {
        //
    }
}
