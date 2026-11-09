<?php

namespace App\Purchasing\Application\Command;

use Symfony\Component\Uid\Uuid;

/**
 * One line as sent: a product or an account, what was typed, the impuesto cargo (null: none) and the retenciones (none,
 * or one per kind). `withholdingTaxIds` null means the deprecated single `withholdingTaxId` decides (observations PRD
 * §6), so a caller of the old shape keeps working.
 */
final readonly class PurchaseLineContents
{
    /**
     * @param list<Uuid>|null $withholdingTaxIds
     */
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
        /** Deprecated: one retención. */
        public ?Uuid $withholdingTaxId,
        public ?array $withholdingTaxIds = null,
        /** A copy (an order converted) passes on its line's code snapshot; null takes the product's code. */
        public ?string $productCode = null,
    ) {
    }
}
