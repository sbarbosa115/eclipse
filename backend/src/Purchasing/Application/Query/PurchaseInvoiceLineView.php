<?php

namespace App\Purchasing\Application\Query;

final readonly class PurchaseInvoiceLineView
{
    public function __construct(
        public string $id,
        public int $position,
        public ?string $productId,
        /** "RES-01 · Resma carta" */
        public ?string $productLabel,
        public ?string $accountId,
        /** "513595 · OTROS" */
        public ?string $accountLabel,
        public string $description,
        public string $quantity,
        public string $unitPrice,
        public string $discount,
        public ?string $chargeTaxId,
        public string $chargeTaxName,
        public string $chargeTaxKind,
        public string $chargeTaxRate,
        public ?string $withholdingTaxId,
        public string $withholdingTaxName,
        public string $withholdingTaxKind,
        public string $withholdingTaxRate,
        public string $grossAmount,
        public string $discountAmount,
        public string $subtotalAmount,
        public string $taxAmount,
        public string $withholdingAmount,
        public string $totalAmount,
    ) {
    }
}
