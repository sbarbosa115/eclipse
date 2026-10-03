<?php

namespace App\Ledger\UI\Http\Output;

use App\Ledger\Application\Query\JournalEntryView;

final readonly class JournalEntryOutput
{
    /**
     * @param list<JournalLineOutput> $lines
     */
    public function __construct(
        public string $id,
        public int $number,
        public string $date,
        /** sales_invoice, cash_receipt, purchase_invoice, supplier_payment… */
        public string $sourceType,
        public string $sourceId,
        public string $sourceNumber,
        public string $description,
        /** The entry this one reverses (a void), if any. */
        public ?string $reversesId,
        public string $totalDebit,
        public string $totalCredit,
        public array $lines,
    ) {
    }

    public static function of(JournalEntryView $v): self
    {
        return new self($v->id, $v->number, $v->date, $v->sourceType, $v->sourceId, $v->sourceNumber, $v->description, $v->reversesId, $v->totalDebit, $v->totalCredit, array_map(JournalLineOutput::of(...), $v->lines));
    }
}
