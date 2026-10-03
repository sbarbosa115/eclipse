<?php

namespace App\Ledger\Application\Posting;

use Symfony\Component\Uid\Uuid;

/**
 * Placeholder until the "ledger" item implements posting. Replace, do not extend. Tests of other items use
 * tests/Support/FakeJournalPoster.
 */
final class UnimplementedJournalPoster implements JournalPoster
{
    public function post(EntryDraft $draft): Uuid
    {
        throw new \LogicException('Posting is built by the "ledger" item.');
    }

    public function reverse(Uuid $companyId, Uuid $entryId, \DateTimeImmutable $date, Uuid $userId, string $description): Uuid
    {
        throw new \LogicException('Posting is built by the "ledger" item.');
    }

    public function isOpen(Uuid $companyId, \DateTimeImmutable $date): bool
    {
        return true;
    }
}
