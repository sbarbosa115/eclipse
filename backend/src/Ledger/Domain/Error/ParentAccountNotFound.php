<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** A new account names a parent the chart does not have. */
final class ParentAccountNotFound extends Refused
{
    public function __construct()
    {
        parent::__construct('parent_account_not_found', 'The chart has no account with this parent code.');
    }
}
