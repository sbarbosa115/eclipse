<?php

namespace App\Reporting\UI\Http\Output;

use App\Reporting\Application\Tax\WithholdingGroup;

/** A month and kind of retención: its rows, totals, and the books' figure for the kind's accounts with the difference. */
final readonly class WithholdingReportGroupOutput
{
    /**
     * @param list<WithholdingReportRowOutput> $rows
     */
    public function __construct(
        /** YYYY-MM */
        public string $month,
        /** retefuente, reteiva, reteica */
        public string $kind,
        public array $rows,
        public string $totalBase,
        public string $totalAmount,
        /** Según libros: what the kind's accounts (2365/2367/2368 practicadas, 135515/17/18 sufridas) moved in the month. */
        public string $ledgerAmount,
        /** ledger_amount − total_amount: zero when the books and the documents agree. */
        public string $difference,
    ) {
    }

    public static function of(WithholdingGroup $g): self
    {
        return new self(
            (string) $g->month,
            $g->kind->value,
            array_map(WithholdingReportRowOutput::of(...), $g->rows),
            $g->totalBase->toString(),
            $g->totalAmount->toString(),
            $g->ledgerAmount->toString(),
            $g->difference()->toString(),
        );
    }
}
