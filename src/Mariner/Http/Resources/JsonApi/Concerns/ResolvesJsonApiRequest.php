<?php

namespace Mariner\Http\Resources\JsonApi\Concerns;

use Mariner\Http\Request;
use Mariner\Http\Resources\JsonApi\JsonApiRequest;

trait ResolvesJsonApiRequest
{
    /**
     * Resolve a JSON API request instance from the given HTTP request.
     *
     * @return \Mariner\Http\Resources\JsonApi\JsonApiRequest
     */
    protected function resolveJsonApiRequestFrom(Request $request)
    {
        return $request instanceof JsonApiRequest
            ? $request
            : JsonApiRequest::createFrom($request);
    }
}
