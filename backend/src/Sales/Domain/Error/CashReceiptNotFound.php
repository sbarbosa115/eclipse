<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/** No recibo de caja with this id in the company (another company's id is "not found" too). */
final class CashReceiptNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('cash_receipt_not_found', 'Cash receipt not found.');
    }
}
