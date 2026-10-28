<?php

namespace Mariner\Core\Server;

/** A request once it is answered: what HttpServer::onRequest() observers hear. */
final readonly class ServedRequest
{
    public function __construct(
        public string $method,
        public string $path,
        /** 0 when the request was refused before an answer was made. */
        public int $status,
        public float $seconds,
    ) {}
}
