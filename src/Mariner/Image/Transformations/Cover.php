<?php

namespace Mariner\Image\Transformations;

use Mariner\Contracts\Image\Transformation;

class Cover implements Transformation
{
    /**
     * @param  positive-int  $width
     * @param  positive-int  $height
     */
    public function __construct(
        public readonly int $width,
        public readonly int $height,
    ) {
        //
    }
}
