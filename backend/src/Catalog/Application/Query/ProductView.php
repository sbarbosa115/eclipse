<?php

namespace App\Catalog\Application\Query;

/**
 * A product as a document line reads it. sale_price is the list price; when price_includes_tax, a line's unit value
 * is that price net of the charge tax (§4.3), which the "catalog" item computes in unitPriceNetOfTax.
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
    ) {
    }
}
