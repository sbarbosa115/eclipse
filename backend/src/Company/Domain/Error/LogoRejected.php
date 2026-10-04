<?php

namespace App\Company\Domain\Error;

use App\Shared\Domain\Error\UnsupportedMedia;

/** The logo is not a PNG or JPEG image, whatever the file is called. */
final class LogoRejected extends UnsupportedMedia
{
    public function __construct()
    {
        parent::__construct('logo_unsupported', 'The logo must be a PNG or JPEG image.');
    }
}
