<?php

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Domain\Money\Quantity;

/**
 * DECIMAL(18,4) ⇄ Quantity.
 *
 * @extends DecimalObjectType<Quantity>
 */
final class QuantityType extends DecimalObjectType
{
    public const NAME = 'quantity';

    protected function scale(): int
    {
        return 4;
    }

    protected function fromString(string $value): Quantity
    {
        return Quantity::of($value);
    }

    protected function className(): string
    {
        return Quantity::class;
    }
}
