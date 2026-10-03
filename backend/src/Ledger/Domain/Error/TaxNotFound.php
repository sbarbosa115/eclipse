<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class TaxNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('tax_not_found', 'Tax not found.');
    }
}
