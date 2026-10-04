<?php

namespace App\Sales\UI\Http\Output;

use App\Shared\Domain\Model\CommercialLine;

/** A line with its taxes as the invoice copied them and its share of the totals. */
final readonly class SalesInvoiceLineOutput
{
    public function __construct(
        public string $id,
        public int $position,
        public ?string $productId,
        /** "código · nombre" of the product, for the form; null when it is no longer in the catalog. */
        public ?string $productLabel,
        public string $description,
        public string $quantity,
        public string $unitPrice,
        /** % Descuento */
        public string $discount,
        public ?string $chargeTaxId,
        public string $chargeTaxName,
        /** A percentage or a value per unit (charge_tax_calculation). */
        public string $chargeTaxRate,
        public string $chargeTaxCalculation,
        public ?string $withholdingTaxId,
        public string $withholdingTaxName,
        public string $withholdingTaxRate,
        public string $grossAmount,
        public string $discountAmount,
        public string $subtotalAmount,
        public string $taxAmount,
        public string $withholdingAmount,
        public string $totalAmount,
    ) {
    }

    public static function of(CommercialLine $l, ?string $productLabel): self
    {
        return new self(
            $l->id()->toRfc4122(),
            $l->position(),
            $l->productId()?->toRfc4122(),
            $productLabel,
            $l->description(),
            $l->quantity()->toString(),
            $l->unitPrice()->toString(),
            $l->discount()->toString(),
            $l->chargeTax()->taxId()?->toRfc4122(),
            $l->chargeTax()->name(),
            $l->chargeTax()->value(),
            $l->chargeTax()->calculation()->value,
            $l->withholdingTax()->taxId()?->toRfc4122(),
            $l->withholdingTax()->name(),
            $l->withholdingTax()->value(),
            $l->grossAmount()->toString(),
            $l->discountAmount()->toString(),
            $l->subtotalAmount()->toString(),
            $l->taxAmount()->toString(),
            $l->withholdingAmount()->toString(),
            $l->totalAmount()->toString(),
        );
    }
}
