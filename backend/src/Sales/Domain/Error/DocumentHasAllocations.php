<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** §4.12: an invoice a receipt was applied to is voided only after the receipts are. */
final class DocumentHasAllocations extends Conflict
{
    public function __construct()
    {
        parent::__construct('document_has_allocations', 'Receipts were applied to this invoice: void them first.');
    }
}
