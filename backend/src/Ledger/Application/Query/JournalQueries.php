<?php

namespace App\Ledger\Application\Query;

use Symfony\Component\Uid\Uuid;

/** The libro diario: entries by date and number, each with all its lines. */
interface JournalQueries
{
    /** @return Page<JournalEntryView> */
    public function page(Uuid $companyId, JournalFilter $filter, int $page, int $perPage): Page;
}
