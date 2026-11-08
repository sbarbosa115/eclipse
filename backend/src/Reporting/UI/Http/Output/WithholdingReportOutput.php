<?php

namespace App\Reporting\UI\Http\Output;

use App\Reporting\Application\Tax\WithholdingReport;

/**
 * Retenciones practicadas or sufridas for a range of months (observations PRD §4.7 a, b): grouped by month then kind
 * (a group appears when the documents or the books have something), and one total per kind over the range.
 */
final readonly class WithholdingReportOutput
{
    /**
     * @param list<WithholdingReportGroupOutput> $groups
     * @param list<WithholdingKindTotalOutput>   $totals
     */
    public function __construct(
        /** YYYY-MM */
        public string $from,
        public string $to,
        /** practiced or suffered */
        public string $side,
        /** The ?kind= filter, null for every kind. */
        public ?string $kind,
        public array $groups,
        public array $totals,
        /** Voided invoices issued in the period with retenciones, left out ("3 facturas anuladas en el periodo no se incluyen"). */
        public int $voidedCount,
    ) {
    }

    public static function of(WithholdingReport $r): self
    {
        return new self(
            $r->range->from(),
            $r->range->to(),
            $r->side->value,
            $r->kind?->value,
            array_map(WithholdingReportGroupOutput::of(...), $r->groups),
            array_map(WithholdingKindTotalOutput::of(...), $r->totals),
            $r->voidedCount,
        );
    }
}
