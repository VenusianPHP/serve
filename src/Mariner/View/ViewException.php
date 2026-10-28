<?php

namespace Mariner\View;

use ErrorException;
use Voyager\Vessel\ControlPanel;
use Voyager\NutsAndBolts\Reflector;

class ViewException extends ErrorException
{
    /**
     * Report the exception.
     *
     * @return bool|null
     */
    public function report()
    {
        $exception = $this->getPrevious();

        if (Reflector::isCallable($reportCallable = [$exception, 'report'])) {
            return ControlPanel::getInstance()->call($reportCallable);
        }

        return false;
    }

    /**
     * Render the exception into an HTTP response.
     *
     * @param  \Mariner\Http\Request  $request
     * @return \Mariner\Http\Response|null
     */
    public function render($request)
    {
        $exception = $this->getPrevious();

        if ($exception && method_exists($exception, 'render')) {
            return $exception->render($request);
        }

        return null;
    }
}
