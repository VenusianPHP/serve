<?php

namespace Venusian\Serve\Tests\Mail\Fixtures;

use Mariner\Mail\Mailable;

class TestMail extends Mailable
{
    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('view');
    }
}
