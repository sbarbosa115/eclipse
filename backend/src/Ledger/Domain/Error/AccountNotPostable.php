<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** Entries post to active subcuentas and auxiliares only, never to a class, group or cuenta that groups others. */
final class AccountNotPostable extends Conflict
{
    public function __construct(private readonly string $accountCode)
    {
        parent::__construct('account_not_postable', \sprintf('Account %s is inactive or groups other accounts.', $accountCode));
    }

    public function details(): array
    {
        return ['code' => $this->accountCode];
    }
}
