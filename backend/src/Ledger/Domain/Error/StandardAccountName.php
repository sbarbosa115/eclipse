<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** The PUC's own accounts keep their official names; only the company's accounts are renamed. */
final class StandardAccountName extends Conflict
{
    public function __construct()
    {
        parent::__construct('account_standard', 'A PUC account keeps the name the Decreto gives it.');
    }
}
