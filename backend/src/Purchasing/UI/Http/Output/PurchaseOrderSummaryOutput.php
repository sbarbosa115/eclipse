<?php

namespace App\Purchasing\UI\Http\Output;

use App\Purchasing\Application\Query\PurchaseOrderSummary;

/** A row of the órdenes de compra list. */
final readonly class PurchaseOrderSummaryOutput
{
    public function __construct(
        public string $id,
        /** draft, emitted, converted, voided */
        public string $status,
        /** OC-3; null on a draft. */
        public ?string $number,
        public string $terceroId,
        public string $terceroName,
        public string $issueDate,
        public ?string $expectedDate,
        public string $netTotal,
        public ?string $convertedInvoiceId,
    ) {
    }

    public static function of(PurchaseOrderSummary $s): self
    {
        return new self($s->id, $s->status, $s->number, $s->terceroId, $s->terceroName, $s->issueDate, $s->expectedDate, $s->netTotal, $s->convertedInvoiceId);
    }
}
