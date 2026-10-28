<?php

namespace Mariner\Core\Server\Http1;

/** How much of a request the parser accepts before answering with an error instead. */
final readonly class Limits
{
    public function __construct(
        /** Bytes in the request line, beyond which the target is too long: 414. */
        public int $request_line = 8_192,
        /** Bytes in the request line and headers together, or in a body's trailers: 431. */
        public int $header_bytes = 16_384,
        /** Header fields in one request: 431. */
        public int $headers = 100,
        /** Bytes in a body, sent whole or chunked: 413. */
        public int $body = 10_485_760,
    ) {}
}
