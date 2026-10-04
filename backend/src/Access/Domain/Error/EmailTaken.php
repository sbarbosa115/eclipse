<?php

namespace App\Access\Domain\Error;

use App\Shared\Domain\Error\InvalidValue;

/** Someone already signs in with this e-mail (in this or another company). */
final class EmailTaken extends InvalidValue
{
    public function __construct()
    {
        parent::__construct('email', 'This e-mail is already registered.');
    }
}
