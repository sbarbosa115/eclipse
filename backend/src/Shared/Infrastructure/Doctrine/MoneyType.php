<?php

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Domain\Money\Money;

/**
 * DECIMAL(18,2) ⇄ Money.
 *
 * @extends DecimalObjectType<Money>
 */
final class MoneyType extends DecimalObjectType
{
    public const NAME = 'money';

    protected function scale(): int
    {
        return 2;
    }

    protected function fromString(string $value): Money
    {
        return Money::of($value);
    }

    protected function className(): string
    {
        return Money::class;
    }
}
