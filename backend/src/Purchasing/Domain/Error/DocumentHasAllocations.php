<?php

namespace App\Purchasing\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** §4.12: an invoice can be voided only while no payment is allocated to it. */
final class DocumentHasAllocations extends Conflict
{
    public function __construct()
    {
        parent::__construct('document_has_allocations', 'Payments are allocated to this invoice: void them first.');
    }
}
