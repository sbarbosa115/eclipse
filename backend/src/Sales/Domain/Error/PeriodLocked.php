<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** §4.1, AC-9: nothing is emitted or voided with a date on or before the fecha de bloqueo (same code as the ledger's). */
final class PeriodLocked extends Conflict
{
    public function __construct(private readonly \DateTimeImmutable $date)
    {
        parent::__construct('period_locked', \sprintf('The books are locked on %s.', $date->format('Y-m-d')));
    }

    public function details(): array
    {
        return ['date' => $this->date->format('Y-m-d')];
    }
}
