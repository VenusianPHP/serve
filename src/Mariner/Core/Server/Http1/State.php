<?php

namespace Mariner\Core\Server\Http1;

/** Where the parser is in the request it is reading. */
enum State
{
    case Head;
    case Body;
    case ChunkSize;
    case ChunkData;
    case ChunkEnd;
    case Trailers;
    case Done;
}
