<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/**
 * One line: the product, what was typed (decimal strings) and the taxes chosen: the impuesto cargo (null: none) and the
 * retenciones (none, or one per kind). `withholdingTaxIds` null means the deprecated single `withholdingTaxId` decides
 * (observations PRD §6), so a caller of the old shape keeps working.
 */
final readonly class SalesInvoiceLineData
{
    /**
     * @param list<Uuid>|null $withholdingTaxIds
     */
    public function __construct(
        public ?Uuid $productId,
        public string $description,
        public string $quantity,
        public string $unitPrice,
        public string $discount,
        public ?Uuid $chargeTaxId,
        public ?Uuid $withholdingTaxId,
        public ?array $withholdingTaxIds = null,
    ) {
    }
}
