<?php

namespace App\Purchasing\Domain\Model;

use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use Symfony\Component\Uid\Uuid;

/**
 * What a draft's line says, already checked against the catalogs: a product or an expense account (never both), and
 * the copies of its taxes (§4.10): the impuesto cargo and the retenciones (none, or one per kind, in order).
 */
final readonly class PurchaseLineDraft
{
    /**
     * @param list<TaxSnapshot> $withholdings
     */
    public function __construct(
        public ?Uuid $productId,
        public ?Uuid $accountId,
        public string $description,
        public Quantity $quantity,
        public UnitPrice $unitPrice,
        public Rate $discount,
        public TaxSnapshot $chargeTax,
        public array $withholdings = [],
        /** The product's code as it was when the line was written (the snapshot a copy passes on); null on a line by account. */
        public ?string $productCode = null,
    ) {
    }
}
