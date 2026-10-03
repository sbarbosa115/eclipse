<?php

namespace App\Ledger\Application\Report;

final readonly class TrialBalance
{
    /**
     * @param list<TrialBalanceRow> $rows every account with a balance or a movement, and every parent of one, in code order
     */
    public function __construct(
        public ?string $from,
        public string $to,
        public array $rows,
        public string $totalDebit,
        public string $totalCredit,
        public bool $balanced,
    ) {
    }
}
