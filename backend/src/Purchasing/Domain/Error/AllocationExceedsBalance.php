<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\Refused;
use App\Shared\Domain\Money\Money;

/** A payment cannot pay more than is owed, and a voided payment cannot restore more than was paid (§9 Q16). */
final class AllocationExceedsBalance extends Refused
{
    public function __construct(private readonly Money $amount, private readonly Money $available)
    {
        parent::__construct('allocation_exceeds_balance', \sprintf('%s is more than the %s available.', $amount, $available));
    }

    public function details(): array
    {
        return ['amount' => $this->amount->toString(), 'available' => $this->available->toString()];
    }
}
