<?php

namespace App\Catalog\Application\Command;

use Symfony\Component\Uid\Uuid;

/** The full form saved over an existing product: every field is written, a null tax or account is "none". */
final readonly class UpdateProduct
{
    public function __construct(
        public Uuid $companyId,
        public Uuid $productId,
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
