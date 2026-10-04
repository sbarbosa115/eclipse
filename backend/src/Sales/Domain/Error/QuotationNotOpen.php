<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** The action needs an emitted quotation not yet decided (accept, reject, void, convert); sending also needs a valid offer. */
final class QuotationNotOpen extends Conflict
{
    public function __construct()
    {
        parent::__construct('quotation_not_open', 'The quotation is a draft, was already decided or voided, or its offer has expired.');
    }
}
