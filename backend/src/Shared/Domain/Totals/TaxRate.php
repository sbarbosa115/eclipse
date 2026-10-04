<?php

namespace App\Shared\Domain\Totals;

use App\Shared\Domain\Money\FourDecimals;
use App\Shared\Domain\Money\Rate;
use Brick\Math\BigDecimal;

/**
 * How a tax on a line is computed (§4.4): a percentage of the discounted base, a percentage of the line's charge tax
 * (ReteIVA), or a fixed value per unit (impoconsumo por valor). "None" is a 0 % rate.
 */
final readonly class TaxRate
{
    private function __construct(
        public TaxCalculation $calculation,
        public BigDecimal $value,
        public TaxBase $base = TaxBase::Subtotal,
    ) {
    }

    public static function percentage(string|int $percent): self
    {
        return new self(TaxCalculation::Percentage, Rate::of($percent)->toBigDecimal());
    }

    /** A percentage of the line's charge tax (ReteIVA). */
    public static function percentageOfTax(string|int $percent): self
    {
        return new self(TaxCalculation::Percentage, Rate::of($percent)->toBigDecimal(), TaxBase::ChargeTax);
    }

    public static function perUnit(string|int $value): self
    {
        $decimal = FourDecimals::parse($value);
        if ($decimal->isNegative()) {
            throw new \InvalidArgumentException('A tax per unit is not negative.');
        }

        return new self(TaxCalculation::PerUnit, $decimal);
    }

    public static function none(): self
    {
        return self::percentage(0);
    }

    /**
     * The exact tax on a line: no rounding here. $chargeTax is the line's exact charge tax, the base of a ReteIVA.
     */
    public function on(BigDecimal $base, BigDecimal $quantity, ?BigDecimal $chargeTax = null): BigDecimal
    {
        if (TaxBase::ChargeTax === $this->base) {
            $base = $chargeTax ?? throw new \LogicException('A tax on the charge tax needs the charge tax.');
        }

        return match ($this->calculation) {
            TaxCalculation::Percentage => $base->multipliedBy($this->value)->dividedBy(100, $base->getScale() + $this->value->getScale() + 2),
            TaxCalculation::PerUnit => $quantity->multipliedBy($this->value),
        };
    }
}
