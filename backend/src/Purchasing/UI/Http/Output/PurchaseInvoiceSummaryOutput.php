<?php

namespace App\Purchasing\UI\Http\Output;

use App\Purchasing\Application\Query\PurchaseInvoiceSummary;

/** A row of the purchase invoice list. */
final readonly class PurchaseInvoiceSummaryOutput
{
    public function __construct(
        public string $id,
        /** draft, emitted, partially_paid, paid, voided */
        public string $status,
        /** The internal number (FC-12); null on a draft. */
        public ?string $number,
        public string $terceroId,
        public string $terceroName,
        public ?string $supplierInvoiceNumber,
        public string $issueDate,
        public ?string $dueDate,
        public string $netTotal,
        public string $paidAmount,
        /** What is still owed: total neto − paid; 0 on a voided invoice. */
        public string $balance,
    ) {
    }

    public static function of(PurchaseInvoiceSummary $s): self
    {
        return new self($s->id, $s->status, $s->number, $s->terceroId, $s->terceroName, $s->supplierInvoiceNumber, $s->issueDate, $s->dueDate, $s->netTotal, $s->paidAmount, $s->balance);
    }
}
