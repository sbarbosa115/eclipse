<?php

namespace App\Sales\Domain\Error;

use App\Shared\Domain\Error\NotAllowed;

/** §8: the accountant reads sales invoices; the owner and billing users write, emit, send and void them. */
final class SalesInvoicesAreReadOnly extends NotAllowed
{
    public function __construct()
    {
        parent::__construct('forbidden', 'Your role cannot change, emit or void sales invoices.');
    }
}
