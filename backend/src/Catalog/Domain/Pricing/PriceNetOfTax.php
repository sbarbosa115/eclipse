<?php

namespace App\Catalog\Domain\Pricing;

use App\Shared\Domain\Money\UnitPrice;
use App\Shared\Domain\Totals\TaxCalculation;
use App\Shared\Domain\Totals\TaxRate;
use Brick\Math\RoundingMode;

/**
 * The unit value a document line starts from (§4.3). A product's price may include its charge tax ("Incluir IVA en el
 * precio"); the line must then carry the price net of that tax, to four decimals, so that a line of one unit totals
 * the list price after the document's single rounding:
 *
 *     percentage tax     net = price / (1 + rate / 100)       119 000 at 19 % → 100 000.0000
 *     per-unit tax       net = price − value                  10 000 with 500 → 9 500.0000
 *
 * Four decimals are enough: the error (at most 0.00005 × 1.19) is far under the half cent the document rounds to.
 */
final class PriceNetOfTax
{
    private function __construct()
    {
    }

    /**
     * @param TaxRate|null $charge the product's charge tax; null when it has none
     *
     * @throws \InvalidArgumentException a per-unit tax larger than the price
     */
    public static function of(UnitPrice $listPrice, bool $includesTax, ?TaxRate $charge): UnitPrice
    {
        if (!$includesTax || null === $charge) {
            return $listPrice;
        }

        $price = $listPrice->toBigDecimal();
        if (TaxCalculation::PerUnit === $charge->calculation) {
            $net = $price->minus($charge->value);
            if ($net->isNegative()) {
                throw new \InvalidArgumentException('The tax per unit is larger than the price that includes it.');
            }

            return UnitPrice::of((string) $net->toScale(4, RoundingMode::HalfUp));
        }

        $divisor = $charge->value->dividedBy(100, $charge->value->getScale() + 2)->plus(1);

        return UnitPrice::of((string) $price->dividedBy($divisor, 4, RoundingMode::HalfUp));
    }
}
