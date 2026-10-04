<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\InvalidValue;

/** A draft may wait for it, but an invoice is emitted with the supplier's own number (§4.10, §6). */
final class SupplierInvoiceNumberRequired extends InvalidValue
{
    public function __construct()
    {
        parent::__construct('supplier_invoice_number', 'Write the supplier\'s invoice number.');
    }
}
