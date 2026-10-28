<?php

namespace Mariner\Image\Transformations;

use Mariner\Contracts\Image\Transformation;

class Blur implements Transformation
{
    /**
     * @param  positive-int  $amount
     */
    public function __construct(public readonly int $amount)
    {
        //
    }
}
