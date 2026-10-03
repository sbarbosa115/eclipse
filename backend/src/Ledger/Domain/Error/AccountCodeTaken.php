<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** Two accounts of a company never share a code. */
final class AccountCodeTaken extends Conflict
{
    public function __construct()
    {
        parent::__construct('account_code_taken', 'The chart already has an account with this code.');
    }
}
