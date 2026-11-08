<?php

namespace App\Sales\Domain\Model;

use App\Shared\Domain\Model\TaxSnapshot;
use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;
use Symfony\Component\Uid\Uuid;

/**
 * A line as a draft is given it: the product, what was typed, and the taxes already copied (TaxSnapshot): its impuesto
 * cargo and its retenciones (none, or one per kind, in order).
 */
final readonly class InvoiceLineDraft
{
    /**
     * @param list<TaxSnapshot> $withholdings
     */
    public function __construct(
        public ?Uuid $productId,
        public string $description,
        public Quantity $quantity,
        public UnitPrice $unitPrice,
        public Rate $discount,
        public TaxSnapshot $chargeTax,
        public array $withholdings = [],
        /** The product's code as it was when the line was written (the snapshot a copy passes on); null on a free line. */
        public ?string $productCode = null,
    ) {
    }
}
