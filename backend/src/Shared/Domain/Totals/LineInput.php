<?php

namespace App\Shared\Domain\Totals;

use App\Shared\Domain\Money\Quantity;
use App\Shared\Domain\Money\Rate;
use App\Shared\Domain\Money\UnitPrice;

/**
 * What a document line contributes to the totals: cantidad, valor unitario, % descuento, impuesto cargo and its
 * retenciones (none, or one per kind: ReteFuente, ReteIVA, ReteICA), in the order the line lists them.
 */
final readonly class LineInput
{
    /**
     * @param list<TaxRate> $withholdings
     */
    public function __construct(
        public Quantity $quantity,
        public UnitPrice $unitPrice,
        public Rate $discount,
        public TaxRate $charge,
        public array $withholdings,
    ) {
    }
}
