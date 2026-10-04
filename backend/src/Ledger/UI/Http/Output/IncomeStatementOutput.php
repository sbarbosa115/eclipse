<?php

namespace App\Ledger\UI\Http\Output;

use App\Ledger\Application\Report\IncomeStatement;

final readonly class IncomeStatementOutput
{
    /**
     * @param list<StatementSectionOutput> $sections classes 4, 6, 7, 5
     */
    public function __construct(
        public string $from,
        public string $to,
        public array $sections,
        public string $revenue,
        public string $costs,
        public string $expenses,
        public string $grossProfit,
        public string $netIncome,
    ) {
    }

    public static function of(IncomeStatement $s): self
    {
        return new self($s->from, $s->to, array_map(StatementSectionOutput::of(...), $s->sections), $s->revenue, $s->costs, $s->expenses, $s->grossProfit, $s->netIncome);
    }
}
