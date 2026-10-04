<?php

namespace App\Ledger\Domain\Repository;

use App\Ledger\Domain\Model\JournalEntry;
use Symfony\Component\Uid\Uuid;

interface JournalEntryRepository
{
    /** @throws \App\Ledger\Domain\Error\JournalEntryNotFound also for another company's entry */
    public function get(Uuid $companyId, Uuid $entryId): JournalEntry;

    public function isReversed(Uuid $companyId, Uuid $entryId): bool;

    public function add(JournalEntry $entry): void;
}
