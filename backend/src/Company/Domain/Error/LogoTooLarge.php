<?php

namespace App\Company\Domain\Error;

use App\Shared\Domain\Error\TooLarge;

final class LogoTooLarge extends TooLarge
{
    public function __construct()
    {
        parent::__construct('logo_too_large', 'The logo must weigh 2 MB or less.');
    }
}
