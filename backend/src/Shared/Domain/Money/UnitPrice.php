<?php

namespace App\Shared\Domain\Money;

use Brick\Math\BigDecimal;

/**
 * The value of one unit on a document line or a product: zero or more, four decimals ("1000.1234"), so a price
 * net of an included IVA keeps its precision until the document rounds once.
 */
final readonly class UnitPrice implements \Stringable
{
    private function __construct(private BigDecimal $value)
    {
    }

    public static function of(string|int $value): self
    {
        $decimal = FourDecimals::parse($value);
        if ($decimal->isNegative()) {
            throw new \InvalidArgumentException(\sprintf('A unit price is not negative, "%s" is.', $value));
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
