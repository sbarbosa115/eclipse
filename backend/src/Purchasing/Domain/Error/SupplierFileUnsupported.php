<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\UnsupportedMedia;

/** Judged by the bytes, never by the name. */
final class SupplierFileUnsupported extends UnsupportedMedia
{
    public function __construct()
    {
        parent::__construct('attachment_unsupported', 'Attach the supplier\'s invoice as a PDF or an XML file.');
    }
}
