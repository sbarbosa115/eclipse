<?php

namespace App\Ledger\UI\Http\Output;

use App\Ledger\Application\Report\TrialBalance;

final readonly class TrialBalanceOutput
{
    /**
     * @param list<TrialBalanceRowOutput> $rows every account with a balance or a movement and its parents, in code order
     */
    public function __construct(
        public ?string $from,
        public string $to,
        public array $rows,
        /** Σ débitos of the period. */
        public string $totalDebit,
        public string $totalCredit,
        /** Σ débitos = Σ créditos. */
        public bool $balanced,
    ) {
    }

    public static function of(TrialBalance $b): self
    {
        return new self($b->from, $b->to, array_map(TrialBalanceRowOutput::of(...), $b->rows), $b->totalDebit, $b->totalCredit, $b->balanced);
    }
}
