<?php

namespace App\Purchasing\Application\Query;

/** A purchase order with everything the editor, the detail and the PDF show. */
final readonly class PurchaseOrderView
{
    /**
     * @param list<PurchaseInvoiceLineView> $lines
     */
    public function __construct(
        public string $id,
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
        public ?string $convertedInvoiceId,
        public string $createdAt,
        public ?string $emittedAt,
        public ?string $voidedAt,
        public ?string $voidReason,
    ) {
    }
}
