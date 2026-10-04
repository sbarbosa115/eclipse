<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** An account a posting rule posts to cannot be deactivated: documents would post to an inactive account. */
final class AccountInPostingRule extends Conflict
{
    public function __construct()
    {
        parent::__construct('account_in_posting_rule', 'A posting rule points at this account: point it elsewhere first.');
    }
}
