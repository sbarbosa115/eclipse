<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** §5 invariant 4: nothing is posted on or before the fecha de bloqueo contable. */
final class PeriodLocked extends Conflict
{
    public function __construct(private readonly \DateTimeImmutable $lockedUntil)
    {
        parent::__construct('period_locked', \sprintf('The books are locked until %s.', $lockedUntil->format('Y-m-d')));
    }

    public function details(): array
    {
        return ['locked_until' => $this->lockedUntil->format('Y-m-d')];
    }
}
