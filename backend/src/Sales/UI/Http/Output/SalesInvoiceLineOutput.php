<?php

namespace App\Sales\UI\Http\Output;

use App\Shared\Application\Document\LineWithholdingView;
use App\Shared\Domain\Model\CommercialLine;
use App\Shared\UI\Http\Output\LineWithholdingOutput;

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
        /** Deprecated: the line's first retención (withholdings[0]); "Ninguno" and null when it has none. */
        public ?string $withholdingTaxId,
        public string $withholdingTaxName,
        public string $withholdingTaxRate,
        public string $grossAmount,
        public string $discountAmount,
        public string $subtotalAmount,
        public string $taxAmount,
        public string $withholdingAmount,
        public string $totalAmount,
        /** The product's código when the line was written (observations PRD §4.4). */
        public ?string $productCode = null,
        /**
         * The line's retenciones (none, or one per kind), each with its base and its share.
         *
         * @var list<LineWithholdingOutput>
         */
        public array $withholdings = [],
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
            $l->productCode(),
            array_map(LineWithholdingOutput::of(...), LineWithholdingView::ofLine($l)),
        );
    }
}
