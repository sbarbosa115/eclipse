<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** A voided payable is owed no more. */
final class PayableVoided extends Refused
{
    public function __construct()
    {
        parent::__construct('payable_voided', 'The payable was voided with its invoice.');
    }
}
