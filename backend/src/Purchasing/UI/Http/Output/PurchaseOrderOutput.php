<?php

namespace App\Purchasing\UI\Http\Output;

use App\Shared\UI\Http\Output\WithholdingSummaryOutput;

/** An orden de compra, whole: header, lines (as a purchase invoice's), totals, conversion and audit. */
final readonly class PurchaseOrderOutput
{
    /**
     * @param list<PurchaseInvoiceLineOutput> $lines
     * @param list<WithholdingSummaryOutput>  $withholdingsByTax
     */
    public function __construct(
        public string $id,
        /** draft, emitted, converted, voided */
        public string $status,
        public ?string $number,
        public string $terceroId,
        public string $terceroName,
        public string $issueDate,
        public ?string $expectedDate,
        public ?string $notes,
        public string $grossTotal,
        public string $discountTotal,
        public string $subtotal,
        public string $taxTotal,
        public string $withholdingTotal,
        public string $netTotal,
        public array $lines,
        public array $withholdingsByTax,
        /** The draft purchase invoice it became; set once. */
        public ?string $convertedInvoiceId,
        public string $createdAt,
        public ?string $emittedAt,
        public ?string $voidedAt,
        public ?string $voidReason,
    ) {
    }
}
