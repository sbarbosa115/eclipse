<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** A document (or a default of the company) already uses this tax: it can only be deactivated. */
final class TaxInUse extends Conflict
{
    public function __construct()
    {
        parent::__construct('tax_in_use', 'A document uses this tax: deactivate it instead of deleting it.');
    }
}
