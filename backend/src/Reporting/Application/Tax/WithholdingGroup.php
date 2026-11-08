<?php

namespace App\Reporting\Application\Tax;

use App\Shared\Domain\Money\Money;

/** One month and kind of a retenciones report: its rows, their totals, and the books' figure with the difference. */
final readonly class WithholdingGroup
{
    /**
     * @param list<WithholdingLine> $rows
     */
    public function __construct(
        /** YYYY-MM, or null for a kind's total over the whole range */
        public ?string $month,
        public WithholdingKind $kind,
        public array $rows,
        public Money $totalBase,
        public Money $totalAmount,
        public Money $ledgerAmount,
    ) {
    }

    /** Según libros − the report: zero when the books and the documents agree. */
    public function difference(): Money
    {
        return $this->ledgerAmount->minus($this->totalAmount);
    }
}
