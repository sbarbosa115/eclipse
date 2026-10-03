<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\Conflict;

/** An entry is reversed at most once (a document is voided once). */
final class EntryAlreadyReversed extends Conflict
{
    public function __construct()
    {
        parent::__construct('entry_already_reversed', 'This entry was already reversed.');
    }
}
