<?php

namespace Mariner\Contracts\Http;

use Mariner\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

interface ExceptionRenderer
{
    /**
     * Report the exception, once, and turn it into the response for the request.
     *
     * Never throws: a failure while rendering is reported and answered with a plain 500.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Throwable  $e
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function handle(Request $request, Throwable $e): Response;
}
