<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** The action needs an emitted purchase order not yet converted (send, void, convert). */
final class PurchaseOrderNotOpen extends Conflict
{
    public function __construct()
    {
        parent::__construct('purchase_order_not_open', 'The purchase order is a draft, was already converted or voided.');
    }
}
