<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** The action needs an emitted quotation whose offer is still valid (accept, reject, void, convert). */
final class QuotationNotOpen extends Conflict
{
    public function __construct()
    {
        parent::__construct('quotation_not_open', 'The quotation is a draft, was already decided or voided, or its offer has expired.');
    }
}
