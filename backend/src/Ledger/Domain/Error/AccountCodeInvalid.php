<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** The code of a new account must be its parent's code plus two digits (§4.1: sub-accounts and auxiliares only). */
final class AccountCodeInvalid extends Refused
{
    public function __construct()
    {
        parent::__construct('account_code_invalid', 'A new account extends its parent code by two digits, under a cuenta (4 digits) or deeper.');
    }
}
