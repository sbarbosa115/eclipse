<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\NotAllowed;

/** Taxes and payment methods are edited by the owner and the accountant (§8); everyone else only reads them. */
final class CatalogEditNotAllowed extends NotAllowed
{
    public function __construct()
    {
        parent::__construct('forbidden', 'Only the owner and the accountant edit taxes and payment methods.');
    }
}
