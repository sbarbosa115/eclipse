<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** A concept posts within its own part of the PUC (ingreso to 41, clientes to 13…), see ConceptAccounts. */
final class AccountNotAllowedForConcept extends Refused
{
    public function __construct()
    {
        parent::__construct('account_not_allowed_for_concept', 'This account does not belong to the PUC class or group the concept posts to.');
    }
}
