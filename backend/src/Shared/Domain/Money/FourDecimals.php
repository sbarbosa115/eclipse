<?php

namespace App\Shared\Domain\Money;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * A decimal kept to four places (§9 Q8: unit prices, quantities and percentages), refused when given more.
 *
 * @internal the shared parsing of Rate, Quantity and UnitPrice
 */
final class FourDecimals
{
    public const SCALE = 4;

    public static function parse(string|int $value): BigDecimal
    {
        $decimal = BigDecimal::of($value);
        if ($decimal->getScale() > self::SCALE && !$decimal->isEqualTo($decimal->toScale(self::SCALE, RoundingMode::Down))) {
            throw new \InvalidArgumentException(\sprintf('"%s" has more than four decimals.', $value));
        }

        return $decimal->toScale(self::SCALE, RoundingMode::Unnecessary);
    }
}
