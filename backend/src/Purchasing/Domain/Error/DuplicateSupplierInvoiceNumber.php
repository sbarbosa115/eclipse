<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\Refused;

/**
 * The supplier's invoice number is unique per supplier (§4.10): the same invoice is not recorded twice. The HTTP layer
 * reports it as a 422 with this code and a violation on `supplier_invoice_number`.
 */
final class DuplicateSupplierInvoiceNumber extends Refused
{
    public const FIELD = 'supplier_invoice_number';

    public function __construct()
    {
        parent::__construct('duplicate_supplier_invoice_number', 'This supplier\'s invoice with this number is already recorded.');
    }
}
