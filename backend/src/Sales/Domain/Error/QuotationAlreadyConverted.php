<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** §4.7, §9 Q17: a quotation converts at most once in this stage. */
final class QuotationAlreadyConverted extends Conflict
{
    public function __construct()
    {
        parent::__construct('quotation_already_converted', 'This quotation was already converted into an invoice.');
    }
}
