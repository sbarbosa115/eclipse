<?php

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Domain\Money\UnitPrice;

/**
 * DECIMAL(18,4) ⇄ UnitPrice.
 *
 * @extends DecimalObjectType<UnitPrice>
 */
final class UnitPriceType extends DecimalObjectType
{
    public const NAME = 'unit_price';

    protected function scale(): int
    {
        return 4;
    }

    protected function fromString(string $value): UnitPrice
    {
        return UnitPrice::of($value);
    }

    protected function className(): string
    {
        return UnitPrice::class;
    }
}
