<?php

namespace Mariner\Http\Resources\Attributes;

use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
class UseResourceCollection
{
    /**
     * The resource collection class to use for the model.
     *
     * @param  string  $class
     */
    public function __construct(public string $class)
    {
        //
    }
}
