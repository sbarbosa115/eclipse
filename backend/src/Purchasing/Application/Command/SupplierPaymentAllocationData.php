<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/** One row of the open payables table: what of the amount paid goes to it. */
final readonly class SupplierPaymentAllocationData
{
    public function __construct(
        public Uuid $payableId,
        /** Pesos, a decimal string. */
        public string $amount,
    ) {
    }
}
