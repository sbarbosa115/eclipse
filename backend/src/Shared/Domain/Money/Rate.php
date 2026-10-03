<?php

namespace App\Shared\Domain\Money;

use Brick\Math\BigDecimal;

/**
 * A percentage from 0 to 100 with four decimals ("19.0000"): a tax rate, a discount.
 */
final readonly class Rate implements \Stringable
{
    private function __construct(private BigDecimal $value)
    {
    }

    public static function of(string|int $percent): self
    {
        $value = FourDecimals::parse($percent);
        if ($value->isNegative() || $value->isGreaterThan(100)) {
            throw new \InvalidArgumentException(\sprintf('A rate is between 0 and 100, "%s" is not.', $percent));
        }

        return new self($value);
    }

    public static function zero(): self
    {
        return self::of(0);
    }

    /** The rate as a fraction: 19 % is 0.19, exact. */
    public function fraction(): BigDecimal
    {
        return $this->value->dividedBy(100, $this->value->getScale() + 2);
    }

    public function isZero(): bool
    {
        return $this->value->isZero();
    }

    public function equals(self $other): bool
    {
        return $this->value->isEqualTo($other->value);
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
