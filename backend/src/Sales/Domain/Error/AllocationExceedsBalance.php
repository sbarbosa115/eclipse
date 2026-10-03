<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** §9 Q16: a receipt never applies more than what is still owed. */
final class AllocationExceedsBalance extends Refused
{
    public function __construct()
    {
        parent::__construct('allocation_exceeds_balance', 'The amount is more than the balance owed.');
    }
}
