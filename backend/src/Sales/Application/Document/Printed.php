<?php

namespace App\Sales\Application\Document;

use App\Shared\Domain\Money\Money;

/** How amounts, rates and dates are printed in Colombia: $ 1.190.000,00, 19 %, 03/10/2026. */
final class Printed
{
    public static function money(Money $amount): string
    {
        $negative = $amount->isNegative();
        [$int, $dec] = explode('.', ltrim($amount->toString(), '-'));

        return ($negative ? '-' : '').'$ '.number_format((int) $int, 0, ',', '.').','.$dec;
    }

    /** A decimal string without its trailing zeros, with a decimal comma: "19.0000" → "19", "2.5000" → "2,5". */
    public static function decimal(string $value): string
    {
        if (str_contains($value, '.')) {
            $value = rtrim(rtrim($value, '0'), '.');
        }
        [$int, $dec] = explode('.', $value) + [1 => ''];

        return number_format((int) $int, 0, ',', '.').('' === $dec ? '' : ','.$dec);
    }

    public static function date(?\DateTimeImmutable $date): string
    {
        return null === $date ? '' : $date->format('d/m/Y');
    }
}
