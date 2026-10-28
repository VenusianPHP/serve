<?php

namespace Mariner\Http\Resources;

use Voyager\NutsAndBolts\Collection;
use JsonSerializable;

class MergeValue
{
    /**
     * The data to be merged.
     *
     * @var array
     */
    public $data;

    /**
     * Create a new merge value instance.
     *
     * @param  \Voyager\NutsAndBolts\Collection|\JsonSerializable|array  $data
     */
    public function __construct($data)
    {
        $this->data = match (true) {
            $data instanceof Collection => $data->all(),
            $data instanceof JsonSerializable => $data->jsonSerialize(),
            default => $data,
        };
    }
}
