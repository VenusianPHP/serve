<?php

namespace Mariner\Core\Server\Http1;

use RuntimeException;

/**
 * A request the parser refuses, with the status to answer it with. The connection answers and
 * then closes: after an error the bytes that follow can't be trusted to start a request.
 */
final class HttpError extends RuntimeException
{
    public function __construct(public readonly int $status, string $reason)
    {
        parent::__construct($reason, $status);
    }
}
