<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** Observations PRD §4.8: a purchase order converts at most once in this stage. */
final class PurchaseOrderAlreadyConverted extends Conflict
{
    public function __construct()
    {
        parent::__construct('purchase_order_already_converted', 'This purchase order was already converted into an invoice.');
    }
}
