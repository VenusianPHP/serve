<?php

namespace Mariner\Contracts\Mail;

interface Attachable
{
    /**
     * Get an attachment instance for this entity.
     *
     * @return \Mariner\Mail\Attachment
     */
    public function toMailAttachment();
}
