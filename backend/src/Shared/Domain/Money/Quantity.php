<?php

namespace App\Shared\Domain\Money;

use Brick\Math\BigDecimal;

/**
 * How many units a document line carries: positive, four decimals ("2.5000").
 */
final readonly class Quantity implements \Stringable
{
    private function __construct(private BigDecimal $value)
    {
    }

    public static function of(string|int $value): self
    {
        $decimal = FourDecimals::parse($value);
        if (!$decimal->isPositive()) {
            throw new \InvalidArgumentException(\sprintf('A quantity is more than zero, "%s" is not.', $value));
        }

        return new self($decimal);
    }

    public function toBigDecimal(): BigDecimal
    {
        return $this->value;
    }

    public function toString(): string
    {
        return (string) $this->value;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
