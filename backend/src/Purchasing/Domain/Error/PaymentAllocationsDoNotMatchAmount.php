<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\Refused;
use App\Shared\Domain\Money\Money;

/** §4.11, §9 Q16: the allocations add up exactly to Valor pagado (no anticipos, no over-payment). */
final class PaymentAllocationsDoNotMatchAmount extends Refused
{
    public function __construct(private readonly Money $allocated, private readonly Money $amount)
    {
        parent::__construct('allocations_do_not_match_amount', \sprintf('The allocations add up to %s, the amount paid is %s.', $allocated, $amount));
    }

    public function details(): array
    {
        return ['allocated_total' => $this->allocated->toString(), 'amount' => $this->amount->toString()];
    }
}
