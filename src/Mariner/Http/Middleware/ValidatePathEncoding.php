<?php

namespace Mariner\Http\Middleware;

use Closure;
use Mariner\Http\Exceptions\MalformedUrlException;
use Mariner\Http\Request;

class ValidatePathEncoding
{
    /**
     * Validate that the incoming request has a valid UTF-8 encoded path.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Closure  $next
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Mariner\Http\Exceptions\MalformedUrlException
     */
    public function handle(Request $request, Closure $next)
    {
        $decodedPath = rawurldecode($request->path());

        if (! mb_check_encoding($decodedPath, 'UTF-8')) {
            throw new MalformedUrlException;
        }

        return $next($request);
    }
}
