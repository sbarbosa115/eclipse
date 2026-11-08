<?php

namespace App\Reporting\UI\Http\Output;

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
        /** Según libros: the kind's accounts (2365/2367/2368 practicadas, 135515/17/18 sufridas) in the month. */
        public string $ledgerAmount,
        public string $difference,
    ) {
    }
}
