<?php

namespace App\Sales\Domain\Model;

use App\Shared\Domain\Money\Money;

/** What a recibo de caja asks to pay of one open receivable (§4.9): the receivable, loaded and locked, and the amount. */
final readonly class ReceivableAllocation
{
    public function __construct(
        public Receivable $receivable,
        public Money $amount,
    ) {
    }
}
