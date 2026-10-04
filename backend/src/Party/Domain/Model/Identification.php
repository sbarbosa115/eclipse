<?php

namespace App\Party\Domain\Model;

use App\Party\Domain\Error\InvalidTerceroValue;
use App\Shared\Domain\Fiscal\CheckDigit;
use App\Shared\Domain\Fiscal\IdentificationType;

/**
 * The number that identifies a tercero and its dígito de verificación (§4.2): only a NIT carries a DV, computed with
 * the DIAN's algorithm and editable by the person.
 */
final class Identification
{
    /** Dots, dashes and spaces are punctuation, not part of the number. */
    public static function normalize(string $number): string
    {
        return strtoupper((string) preg_replace('/[\s.\-]/', '', $number));
    }

    /**
     * @return string|null the DV to store: null for any type but NIT; for a NIT the one given, else the computed one
     */
    public static function checkDigit(IdentificationType $type, string $number, ?string $given): ?string
    {
        if (!$type->hasCheckDigit()) {
            return null;
        }
        $given = null === $given ? '' : trim($given);
        if ('' !== $given) {
            if (1 !== preg_match('/^\d$/', $given)) {
                throw new InvalidTerceroValue('check_digit', 'The verification digit is a single digit.');
            }

            return $given;
        }
        if (1 !== preg_match('/^\d{1,15}$/', $number)) {
            throw new InvalidTerceroValue('identification_number', 'A NIT has only digits.');
        }

        return CheckDigit::of($number);
    }
}
