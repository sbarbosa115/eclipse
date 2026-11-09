<?php

namespace App\Reporting\Application\Tax;

/** Retenciones practicadas or sufridas for a range of months (observations PRD §4.7 a, b). */
final readonly class WithholdingReport
{
    /**
     * @param list<WithholdingGroup> $groups by month, then kind
     * @param list<WithholdingGroup> $totals one per kind over the whole range (no rows)
     */
    public function __construct(
        public WithholdingSide $side,
        public MonthRange $range,
        public ?WithholdingKind $kind,
        public array $groups,
        public array $totals,
        public int $voidedCount,
    ) {
    }

    public function rowCount(): int
    {
        return array_sum(array_map(static fn (WithholdingGroup $g) => \count($g->rows), $this->groups));
    }
}
