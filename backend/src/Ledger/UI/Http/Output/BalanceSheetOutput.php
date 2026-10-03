<?php

namespace App\Ledger\UI\Http\Output;

use App\Ledger\Application\Report\BalanceSheet;

final readonly class BalanceSheetOutput
{
    /**
     * @param list<StatementSectionOutput> $sections classes 1, 2, 3
     */
    public function __construct(
        public string $date,
        public array $sections,
        /** The result of classes 4 to 7 not yet closed into patrimonio. */
        public string $currentEarnings,
        public string $totalAssets,
        public string $totalLiabilities,
        /** Class 3 plus the current earnings. */
        public string $totalEquity,
        /** Activo = pasivo + patrimonio. */
        public bool $balanced,
    ) {
    }

    public static function of(BalanceSheet $s): self
    {
        return new self($s->date, array_map(StatementSectionOutput::of(...), $s->sections), $s->currentEarnings, $s->totalAssets, $s->totalLiabilities, $s->totalEquity, $s->balanced);
    }
}
