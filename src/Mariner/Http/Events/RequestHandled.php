<?php

namespace Mariner\Http\Events;

class RequestHandled
{
    /**
     * The request instance.
     *
     * @var \Mariner\Http\Request
     */
    public $request;

    /**
     * The response instance.
     *
     * @var \Symfony\Component\HttpFoundation\Response
     */
    public $response;

    /**
     * Create a new event instance.
     *
     * @param  \Mariner\Http\Request  $request
     * @param  \Symfony\Component\HttpFoundation\Response  $response
     */
    public function __construct($request, $response)
    {
        $this->request = $request;
        $this->response = $response;
    }
}
