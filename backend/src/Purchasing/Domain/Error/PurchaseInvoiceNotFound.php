<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/** Another company's id answers the same. */
final class PurchaseInvoiceNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('purchase_invoice_not_found', 'Purchase invoice not found.');
    }
}
