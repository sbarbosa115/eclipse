<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

final readonly class PurchaseLineContents
{
    public function __construct(
        public ?Uuid $productId,
        public ?Uuid $accountId,
        public string $description,
        /** Decimal strings, four decimals at most. */
        public string $quantity,
        public string $unitPrice,
        public string $discount,
        /** Null: none. */
        public ?Uuid $chargeTaxId,
        public ?Uuid $withholdingTaxId,
    ) {
    }
}
