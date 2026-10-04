<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\Refused;
use App\Shared\Domain\Money\Money;

/** §4.9, §9 Q16: the allocations add up exactly to Valor recibido (no anticipos, no over-payment). */
final class AllocationsDoNotMatchAmount extends Refused
{
    public function __construct(private readonly Money $allocated, private readonly Money $amount)
    {
        parent::__construct('allocations_do_not_match_amount', \sprintf('The allocations add up to %s, the amount received is %s.', $allocated, $amount));
    }

    public function details(): array
    {
        return ['allocated_total' => $this->allocated->toString(), 'amount' => $this->amount->toString()];
    }
}
