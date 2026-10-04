<?php

namespace App\Company\Domain\Error;

use App\Shared\Domain\Error\InvalidValue;

/** A company with this NIT is already registered. */
final class IdentificationTaken extends InvalidValue
{
    public function __construct()
    {
        parent::__construct('identification_number', 'A company with this identification is already registered.');
    }
}
