<?php

namespace App\Tests\Support;

use App\Ledger\Application\Posting\EntryDraft;
use App\Ledger\Application\Posting\JournalPoster;
use App\Ledger\Application\Posting\Side;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * Records what documents ask to post, for the tests of items that post before the "ledger" item lands (and for unit
 * tests that only care about the lines). It refuses an unbalanced draft like the real poster.
 */
final class FakeJournalPoster implements JournalPoster
{
    /** @var list<EntryDraft> */
    public array $posted = [];

    /** @var list<array{entryId: Uuid, date: \DateTimeImmutable}> */
    public array $reversed = [];

    public ?\DateTimeImmutable $lockedUntil = null;

    public function post(EntryDraft $draft): Uuid
    {
        $debit = $credit = Money::zero();
        foreach ($draft->lines as $line) {
            Side::Debit === $line->side ? $debit = $debit->plus($line->amount) : $credit = $credit->plus($line->amount);
        }
        if (!$debit->equals($credit)) {
            throw new \LogicException(\sprintf('Unbalanced draft: %s debit, %s credit.', $debit, $credit));
        }
        $this->posted[] = $draft;

        return Uuid::v7();
    }

    public function reverse(Uuid $companyId, Uuid $entryId, \DateTimeImmutable $date, Uuid $userId, string $description): Uuid
    {
        $this->reversed[] = ['entryId' => $entryId, 'date' => $date];

        return Uuid::v7();
    }

    public function isOpen(Uuid $companyId, \DateTimeImmutable $date): bool
    {
        return null === $this->lockedUntil || $date->format('Y-m-d') > $this->lockedUntil->format('Y-m-d');
    }
}
