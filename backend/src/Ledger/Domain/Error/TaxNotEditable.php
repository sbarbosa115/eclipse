<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Refused;

/** "Ninguno" is what a line without a tax points at: it is never edited, deactivated or deleted. */
final class TaxNotEditable extends Refused
{
    public function __construct()
    {
        parent::__construct('tax_not_editable', 'The "none" tax is fixed.');
    }
}
