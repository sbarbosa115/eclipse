<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/** Another company's id answers the same. */
final class PayableNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('payable_not_found', 'Payable not found.');
    }
}
