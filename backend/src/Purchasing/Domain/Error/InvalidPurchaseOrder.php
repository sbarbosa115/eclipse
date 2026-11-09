<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\InvalidValues;

/** Values of a purchase order that break a rule only the domain checks, by field path (lines[0].product_id, expected_date…). */
final class InvalidPurchaseOrder extends InvalidValues
{
    public static function field(string $field, string $message): self
    {
        return new self([['field' => $field, 'message' => $message]]);
    }
}
