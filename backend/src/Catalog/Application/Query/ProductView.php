<?php

namespace App\Catalog\Application\Query;

/**
 * A product as a document line reads it. sale_price is the list price; when price_includes_tax, a line's unit value
 * is that price net of the charge tax (§4.3), which the catalog computes in unitPriceNetOfTax (use unitValue()).
 */
final readonly class ProductView
{
    public function __construct(
        public string $id,
        /** producto or servicio */
        public string $type,
        public string $code,
        public string $name,
        public ?string $description,
        public string $unitCode,
        public string $salePrice,
        public bool $priceIncludesTax,
        public ?string $chargeTaxId,
        public ?string $withholdingTaxId,
        public ?string $revenueAccountId,
        public ?string $expenseAccountId,
        public bool $active,
        public ?string $categoryId = null,
        public ?string $categoryName = null,
        /** The line's unit value (see unitValue()); null only in a view built by hand. */
        public ?string $unitPriceNetOfTax = null,
        /** "413595 · Venta de otros": the account's code and name, for forms and lists. */
        public ?string $revenueAccountLabel = null,
        public ?string $expenseAccountLabel = null,
    ) {
    }

    /** What a document line starts its valor unitario from: the price, net of the charge tax when it includes it. */
    public function unitValue(): string
    {
        return $this->unitPriceNetOfTax ?? $this->salePrice;
    }
}
