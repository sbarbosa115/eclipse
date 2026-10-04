<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** One row of the open receivables table: what of the amount received goes to it. */
final readonly class CashReceiptAllocationData
{
    public function __construct(
        public Uuid $receivableId,
        /** Pesos, a decimal string. */
        public string $amount,
    ) {
    }
}
