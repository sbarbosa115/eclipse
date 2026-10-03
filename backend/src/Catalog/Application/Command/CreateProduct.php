<?php

namespace App\Catalog\Application\Command;

use Symfony\Component\Uid\Uuid;

/**
 * A new product or service, from the full form or a document line's quick-create (§4.3). A tax left null is the
 * company's default; a missing unit is the type's.
 */
final readonly class CreateProduct
{
    public function __construct(
        public Uuid $companyId,
        public string $type,
        public string $code,
        public string $name,
        public ?string $description,
        public ?Uuid $categoryId,
        public ?string $unitCode,
        public string $salePrice,
        public bool $priceIncludesTax,
        public ?Uuid $chargeTaxId,
        public ?Uuid $withholdingTaxId,
        public ?Uuid $revenueAccountId,
        public ?Uuid $expenseAccountId,
    ) {
    }
}
