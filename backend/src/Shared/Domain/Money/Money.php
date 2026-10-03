<?php

namespace App\Shared\Domain\Money;

use Brick\Math\BigDecimal;
use Brick\Math\BigNumber;
use Brick\Math\RoundingMode;

/**
 * An amount of Colombian pesos with exactly two decimals, in decimal arithmetic (never a float). It travels as a
 * string ("1190000.00") on the wire and in the database (DECIMAL(18,2)).
 *
 * An amount given with more than two decimals is refused, not rounded: rounding happens once, on purpose, through
 * rounded() (the document totals round once at document level, §4.6).
 */
final readonly class Money implements \Stringable
{
    public const SCALE = 2;

    private function __construct(private BigDecimal $amount)
    {
    }

    public static function of(string|int $amount): self
    {
        $value = BigDecimal::of($amount);
        if ($value->getScale() > self::SCALE && !$value->isEqualTo($value->toScale(self::SCALE, RoundingMode::Down))) {
            throw new \InvalidArgumentException(\sprintf('"%s" has more than two decimals.', $amount));
        }

        return new self($value->toScale(self::SCALE, RoundingMode::Unnecessary));
    }

    /**
     * An exact value (a line's tax, a sum of them) rounded half up to the cent: the one rounding a document does.
     */
    public static function rounded(BigNumber $exact): self
    {
        return new self($exact->toBigDecimal()->toScale(self::SCALE, RoundingMode::HalfUp));
    }

    public static function zero(): self
    {
        return new self(BigDecimal::zero()->toScale(self::SCALE));
    }

    public static function sum(self ...$amounts): self
    {
        $total = self::zero();
        foreach ($amounts as $amount) {
            $total = $total->plus($amount);
        }

        return $total;
    }

    public function plus(self $other): self
    {
        return new self($this->amount->plus($other->amount));
    }

    public function minus(self $other): self
    {
        return new self($this->amount->minus($other->amount));
    }

    public function negated(): self
    {
        return new self($this->amount->negated());
    }

    public function isZero(): bool
    {
        return $this->amount->isZero();
    }

    public function isNegative(): bool
    {
        return $this->amount->isNegative();
    }

    public function isPositive(): bool
    {
        return $this->amount->isPositive();
    }

    public function equals(self $other): bool
    {
        return $this->amount->isEqualTo($other->amount);
    }

    public function isGreaterThan(self $other): bool
    {
        return $this->amount->isGreaterThan($other->amount);
    }

    public function isLessThan(self $other): bool
    {
        return $this->amount->isLessThan($other->amount);
    }

    public function compareTo(self $other): int
    {
        return $this->amount->compareTo($other->amount);
    }

    public function toBigDecimal(): BigDecimal
    {
        return $this->amount;
    }

    public function toString(): string
    {
        return (string) $this->amount;
    }

    public function __toString(): string
    {
        return $this->toString();
    }
}
