<?php

namespace Mariner\Contracts\NutsAndBolts;

interface Responsable
{
    /**
     * Create an HTTP response that represents the object.
     *
     * @param  \Mariner\Http\Request  $request
     * @return \Symfony\Component\HttpFoundation\Response
     */
    public function toResponse($request);
}
