<?php

namespace App\Reporting\UI\Http\Output;

use App\Reporting\Application\Cartera\CarteraDocument;

/** An open receivable or payable: the invoice it belongs to, when it fell due and how late it is. */
final readonly class CarteraDocumentOutput
{
    public function __construct(
        public string $id,
        public string $invoiceId,
        public string $invoiceNumber,
        public string $issueDate,
        public string $dueDate,
        /** What the crédito line was for. */
        public string $amount,
        /** What is still owed as of the date. */
        public string $balance,
        /** Whole days after the due date at the report's date; 0 or less while not yet due. */
        public int $daysOverdue,
        /** current, days1_to30, days31_to60, days61_to90 or over90 */
        public string $bucket,
    ) {
    }

    public static function of(CarteraDocument $d): self
    {
        return new self($d->id, $d->invoiceId, $d->invoiceNumber, $d->issueDate, $d->dueDate, $d->amount, $d->balance, $d->daysOverdue, $d->bucket->value);
    }
}
