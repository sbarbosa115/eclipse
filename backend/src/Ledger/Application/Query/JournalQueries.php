<?php

namespace App\Ledger\Application\Query;

use Symfony\Component\Uid\Uuid;

/** The libro diario: entries by date and number, each with all its lines. */
interface JournalQueries
{
    /** @return Page<JournalEntryView> */
    public function page(Uuid $companyId, JournalFilter $filter, int $page, int $perPage): Page;

    /**
     * A document's own entries (its posting and, once voided, the reversal), by number: the Contabilización tab
     * (observations PRD §4.5). Built by the "journal-entry-tab" item; item 0 leaves it unimplemented.
     *
     * @return list<JournalEntryView>
     */
    public function bySource(Uuid $companyId, string $sourceType, Uuid $sourceId): array;
}
