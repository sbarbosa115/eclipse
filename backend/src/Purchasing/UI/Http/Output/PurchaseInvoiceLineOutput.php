<?php

namespace App\Purchasing\UI\Http\Output;

use App\Purchasing\Application\Query\PurchaseInvoiceLineView;

/** A line as it was saved, its taxes as copied then, and its share of the totals. */
final readonly class PurchaseInvoiceLineOutput
{
    public function __construct(
        public string $id,
        public int $position,
        public ?string $productId,
        public ?string $productLabel,
        public ?string $accountId,
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

    public static function of(PurchaseInvoiceLineView $v): self
    {
        return new self($v->id, $v->position, $v->productId, $v->productLabel, $v->accountId, $v->accountLabel, $v->description, $v->quantity, $v->unitPrice, $v->discount, $v->chargeTaxId, $v->chargeTaxName, $v->chargeTaxKind, $v->chargeTaxRate, $v->withholdingTaxId, $v->withholdingTaxName, $v->withholdingTaxKind, $v->withholdingTaxRate, $v->grossAmount, $v->discountAmount, $v->subtotalAmount, $v->taxAmount, $v->withholdingAmount, $v->totalAmount);
    }
}
