<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\InvalidValues;

/** Values of a recibo de pago that break a rule only the domain checks, by field path (amount, allocations.0.amount…). */
final class InvalidPayment extends InvalidValues
{
    public static function field(string $field, string $message): self
    {
        return new self([['field' => $field, 'message' => $message]]);
    }
}
