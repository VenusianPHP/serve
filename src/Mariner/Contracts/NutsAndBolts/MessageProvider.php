<?php

namespace Mariner\Contracts\NutsAndBolts;

interface MessageProvider
{
    /**
     * Get the messages for the instance.
     *
     * @return \Mariner\Contracts\NutsAndBolts\MessageBag
     */
    public function getMessageBag();
}
