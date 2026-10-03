<?php

namespace App\Sales\Application\Command;

use Symfony\Component\Uid\Uuid;

/** One line: the product, what was typed (decimal strings) and the taxes chosen (null: none). */
final readonly class SalesInvoiceLineData
{
    public function __construct(
        public ?Uuid $productId,
        public string $description,
        public string $quantity,
        public string $unitPrice,
        public string $discount,
        public ?Uuid $chargeTaxId,
        public ?Uuid $withholdingTaxId,
    ) {
    }
}
