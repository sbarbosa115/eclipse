<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/**
 * A subcuenta (or auxiliar) with active auxiliares takes no movements: they go to one of its auxiliares (Colombian
 * practice, observations PRD §4.1).
 */
final class AccountHasAuxiliaries extends Conflict
{
    public function __construct(private readonly string $accountCode)
    {
        parent::__construct('account_has_auxiliaries', \sprintf('Account %s has auxiliaries: post to one of them.', $accountCode));
    }

    public function details(): array
    {
        return ['account_code' => $this->accountCode];
    }
}
