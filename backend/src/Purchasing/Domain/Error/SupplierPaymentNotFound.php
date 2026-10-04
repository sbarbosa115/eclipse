<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\NotFound;

/** No recibo de pago with this id in the company (another company's id is "not found" too). */
final class SupplierPaymentNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('supplier_payment_not_found', 'Supplier payment not found.');
    }
}
