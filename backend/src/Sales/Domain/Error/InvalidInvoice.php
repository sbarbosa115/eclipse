<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\InvalidValues;

/** Values of an invoice that break a rule only the domain checks, by field path (lines.0.product_id, payments.1.due_date…). */
final class InvalidInvoice extends InvalidValues
{
    public static function field(string $field, string $message): self
    {
        return new self([['field' => $field, 'message' => $message]]);
    }
}
