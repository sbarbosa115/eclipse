<?php

namespace App\Catalog\UI\Http\Output;

use App\Catalog\Application\Query\ProductView;

/**
 * A product or service as lists and document lines read it.
 */
final readonly class ProductOutput
{
    public function __construct(
        public string $id,
        /** producto or servicio */
        public string $type,
        public string $code,
        public string $name,
        public ?string $description,
        public ?string $categoryId,
        public ?string $categoryName,
        /** DIAN unit code (94 unidad…) */
        public string $unitCode,
        /** Decimal string, four decimals: the list price. */
        public string $salePrice,
        public bool $priceIncludesTax,
        /** The line's unit value: the sale price, net of the charge tax when it includes it (§4.3). */
        public string $unitPriceNetOfTax,
        public ?string $chargeTaxId,
        public ?string $withholdingTaxId,
        public ?string $revenueAccountId,
        public ?string $expenseAccountId,
        public bool $active,
    ) {
    }

    public static function of(ProductView $v): self
    {
        return new self($v->id, $v->type, $v->code, $v->name, $v->description, $v->categoryId, $v->categoryName, $v->unitCode, $v->salePrice, $v->priceIncludesTax, $v->unitValue(), $v->chargeTaxId, $v->withholdingTaxId, $v->revenueAccountId, $v->expenseAccountId, $v->active);
    }
}
