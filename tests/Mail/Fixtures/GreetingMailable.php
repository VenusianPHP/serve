<?php

namespace Venusian\Serve\Tests\Mail\Fixtures;

use Mariner\Mail\Mailable;

class GreetingMailable extends Mailable
{
    public function build()
    {
        return $this->view('greeting');
    }
}
