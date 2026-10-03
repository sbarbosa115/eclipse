<?php

namespace App\Ledger\Application\Query;

use Symfony\Component\Uid\Uuid;

/** The sums the balance de prueba is built from, read straight from the journal lines. */
interface LedgerMovements
{
    /**
     * One row per account with anything before $from or between $from and $to (inclusive). Without $from, everything
     * up to $to is in the period.
     *
     * @return list<AccountMovement>
     */
    public function byAccount(Uuid $companyId, ?\DateTimeImmutable $from, \DateTimeImmutable $to): array;

    /**
     * The chart's names, levels and natures for these codes and every parent of them.
     *
     * @param list<string> $codes
     *
     * @return array<string, array{name: string, level: string, nature: string}>
     */
    public function accounts(Uuid $companyId, array $codes): array;
}
