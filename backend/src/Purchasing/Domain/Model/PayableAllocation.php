<?php

namespace App\Purchasing\Domain\Model;

use App\Shared\Domain\Money\Money;

/** What a recibo de pago asks to pay of one open payable (§4.11): the payable, loaded and locked, and the amount. */
final readonly class PayableAllocation
{
    public function __construct(
        public Payable $payable,
        public Money $amount,
    ) {
    }
}
