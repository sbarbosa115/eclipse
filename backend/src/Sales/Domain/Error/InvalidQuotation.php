<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\InvalidValues;

/** Values of a quotation that break a rule only the domain checks, by field path (lines.0.product_id, expiry_date…). */
final class InvalidQuotation extends InvalidValues
{
    public static function field(string $field, string $message): self
    {
        return new self([['field' => $field, 'message' => $message]]);
    }
}
