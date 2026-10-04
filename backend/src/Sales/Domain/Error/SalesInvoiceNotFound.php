<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/** No sales invoice with this id in the company (another company's id is "not found" too). */
final class SalesInvoiceNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('sales_invoice_not_found', 'Sales invoice not found.');
    }
}
