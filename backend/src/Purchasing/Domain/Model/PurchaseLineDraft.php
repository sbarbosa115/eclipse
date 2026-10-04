<?php

namespace App\Purchasing\Domain\Model;

use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use Symfony\Component\Uid\Uuid;

/**
 * What a draft's line says, already checked against the catalogs: a product or an expense account (never both), and
 * the copies of its two taxes (§4.10).
 */
final readonly class PurchaseLineDraft
{
    public function __construct(
        public ?Uuid $productId,
        public ?Uuid $accountId,
        public string $description,
        public Quantity $quantity,
        public UnitPrice $unitPrice,
        public Rate $discount,
        public TaxSnapshot $chargeTax,
        public TaxSnapshot $withholdingTax,
    ) {
    }
}
