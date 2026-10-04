<?php

namespace App\Ledger\Domain\Error;

use App\Shared\Domain\Error\NotFound;

final class JournalEntryNotFound extends NotFound
{
    public function __construct()
    {
        parent::__construct('journal_entry_not_found', 'Journal entry not found.');
    }
}
