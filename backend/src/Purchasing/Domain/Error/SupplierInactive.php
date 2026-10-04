<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** An inactive tercero is out of new documents (§4.2). */
final class SupplierInactive extends Refused
{
    public function __construct()
    {
        parent::__construct('supplier_inactive', 'The supplier is inactive: reactivate it first.');
    }
}
