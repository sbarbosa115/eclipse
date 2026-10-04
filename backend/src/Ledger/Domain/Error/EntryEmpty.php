<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Rejected;

/** Every movement of the entry was zero: there is nothing to post. */
final class EntryEmpty extends Rejected
{
    public function __construct()
    {
        parent::__construct('entry_empty', 'An entry needs at least one movement.');
    }
}
