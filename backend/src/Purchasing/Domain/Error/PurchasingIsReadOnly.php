<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\NotAllowed;

/** The owner and billing users write purchase documents; the accountant reads them (§8). */
final class PurchasingIsReadOnly extends NotAllowed
{
    public function __construct()
    {
        parent::__construct('forbidden', 'Your role cannot change purchase invoices.');
    }
}
