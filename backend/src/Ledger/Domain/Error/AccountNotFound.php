<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class AccountNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('account_not_found', 'Account not found.');
    }
}
