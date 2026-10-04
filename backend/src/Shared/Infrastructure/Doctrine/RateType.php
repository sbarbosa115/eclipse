<?php

namespace App\Shared\Infrastructure\Doctrine;

use App\Shared\Domain\Money\Rate;

/**
 * DECIMAL(18,4) ⇄ Rate.
 *
 * @extends DecimalObjectType<Rate>
 */
final class RateType extends DecimalObjectType
{
    public const NAME = 'rate';

    protected function scale(): int
    {
        return 4;
    }

    protected function fromString(string $value): Rate
    {
        return Rate::of($value);
    }

    protected function className(): string
    {
        return Rate::class;
    }
}
