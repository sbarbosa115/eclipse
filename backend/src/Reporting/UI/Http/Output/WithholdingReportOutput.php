<?php

namespace App\Reporting\UI\Http\Output;

/** Retenciones practicadas or sufridas for a range of months (observations PRD §4.7 a, b). */
final readonly class WithholdingReportOutput
{
    /**
     * @param list<WithholdingReportGroupOutput> $groups
     */
    public function __construct(
        /** YYYY-MM */
        public string $from,
        public string $to,
        /** practiced or suffered */
        public string $side,
        public array $groups,
        /** Voided invoices in the period, left out ("3 facturas anuladas en el periodo no se incluyen"). */
        public int $voidedCount,
    ) {
    }
}
