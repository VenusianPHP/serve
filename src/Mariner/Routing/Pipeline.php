<?php

namespace Mariner\Routing;

use Mariner\Contracts\Http\ExceptionRenderer;
use Mariner\Contracts\NutsAndBolts\Responsable;
use Mariner\Http\Request;
use Voyager\Pipeline\Pipeline as BasePipeline;
use Throwable;

/**
 * This extended pipeline catches any exceptions that occur during each slice.
 *
 * The exceptions are converted to HTTP responses for proper middleware handling.
 */
class Pipeline extends BasePipeline
{
    /**
     * Handles the value returned from each pipe before passing it to the next.
     *
     * @param  mixed  $carry
     * @return mixed
     */
    protected function handleCarry(mixed $carry): mixed
    {
        return $carry instanceof Responsable
            ? $carry->toResponse($this->getContainer()->make(Request::class))
            : $carry;
    }

    /**
     * Handle the given exception.
     *
     * @param  mixed  $passable
     * @param  \Throwable  $e
     * @return mixed
     *
     * @throws \Throwable
     */
    protected function handleException(mixed $passable, Throwable $e): mixed
    {
        if (! $this->vessel->isBound(ExceptionRenderer::class) ||
            ! $passable instanceof Request) {
            throw $e;
        }

        $response = $this->vessel->make(ExceptionRenderer::class)->handle($passable, $e);

        if (method_exists($response, 'withException')) {
            $response->withException($e);
        }

        return $response;
    }
}
