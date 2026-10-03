<?php

namespace App\Shared\Domain\Fiscal;

/**
 * The dígito de verificación of a NIT, by the DIAN's algorithm: the digits from the right are weighted by
 * 3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71; the sum modulo 11 gives the DV directly when it is 0 or
 * 1, and 11 minus it otherwise.
 */
final class CheckDigit
{
    private const WEIGHTS = [3, 7, 13, 17, 19, 23, 29, 37, 41, 43, 47, 53, 59, 67, 71];

    public static function of(string $nit): string
    {
        $digits = preg_replace('/\D/', '', $nit) ?? '';
        if ('' === $digits || \strlen($digits) > \count(self::WEIGHTS)) {
            throw new \InvalidArgumentException(\sprintf('"%s" is not a NIT.', $nit));
        }

        $sum = 0;
        foreach (array_reverse(str_split($digits)) as $i => $digit) {
            $sum += (int) $digit * self::WEIGHTS[$i];
        }
        $remainder = $sum % 11;

        return (string) ($remainder > 1 ? 11 - $remainder : $remainder);
    }
}
