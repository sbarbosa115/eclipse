<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** A document already uses this payment method: it can only be deactivated. */
final class PaymentMethodInUse extends Conflict
{
    public function __construct()
    {
        parent::__construct('payment_method_in_use', 'A document uses this payment method: deactivate it instead of deleting it.');
    }
}
