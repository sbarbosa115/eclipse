<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/** Another company's id answers the same. */
final class PurchaseOrderNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('purchase_order_not_found', 'Purchase order not found.');
    }
}
