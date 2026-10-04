<?php

namespace App\Sales\Domain\Model;

use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use Symfony\Component\Uid\Uuid;

/** A line as a draft is given it: the product, what was typed, and the taxes already copied (TaxSnapshot). */
final readonly class InvoiceLineDraft
{
    public function __construct(
        public ?Uuid $productId,
        public string $description,
        public Quantity $quantity,
        public UnitPrice $unitPrice,
        public Rate $discount,
        public TaxSnapshot $chargeTax,
        public TaxSnapshot $withholdingTax,
    ) {
    }
}
