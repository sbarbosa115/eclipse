<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class PaymentMethodNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('payment_method_not_found', 'Payment method not found.');
    }
}
