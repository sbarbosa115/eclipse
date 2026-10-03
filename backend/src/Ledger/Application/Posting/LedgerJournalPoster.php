<?php

namespace App\Ledger\Application\Posting;

use App\Company\Application\Numbering\Numbering;
use App\Ledger\Application\Port\TerceroAccounts;
use App\Ledger\Domain\Error\AccountNotPostable;
use App\Ledger\Domain\Error\EntryAlreadyReversed;
use App\Ledger\Domain\Error\EntryEmpty;
use App\Ledger\Domain\Error\EntryUnbalanced;
use App\Ledger\Domain\Error\PeriodLocked;
use App\Ledger\Domain\Error\PostingRuleMissing;
use App\Ledger\Domain\Model\Account;
use App\Ledger\Domain\Model\JournalEntry;
use App\Ledger\Domain\Repository\AccountRepository;
use App\Ledger\Domain\Repository\JournalEntryRepository;
use App\Ledger\Domain\Repository\LedgerSettingsRepository;
use App\Ledger\Domain\Repository\PostingRuleRepository;
use App\Shared\Domain\Accounting\PostingConcept;
use App\Shared\Domain\Clock;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * The books' one door (see JournalPoster). Everything is checked before the entry takes its number: the lock date,
 * each line's account, and the balance, so a refused entry spends nothing.
 */
final class LedgerJournalPoster implements JournalPoster
{
    public function __construct(
        private readonly AccountRepository $accounts,
        private readonly PostingRuleRepository $rules,
        private readonly JournalEntryRepository $entries,
        private readonly LedgerSettingsRepository $settings,
        private readonly TerceroAccounts $terceros,
        private readonly Numbering $numbering,
        private readonly Clock $clock,
    ) {
    }

    public function post(EntryDraft $draft): Uuid
    {
        $this->assertOpen($draft->companyId, $draft->date);

        $movements = [];
        $debit = $credit = Money::zero();
        foreach ($draft->lines as $line) {
            if ($line->amount->isZero()) {
                continue;
            }
            $account = $this->resolve($draft->companyId, $line);
            if (!$account->isPostable()) {
                throw new AccountNotPostable($account->code());
            }
            $movements[] = [$line, $account];
            Side::Debit === $line->side ? $debit = $debit->plus($line->amount) : $credit = $credit->plus($line->amount);
        }
        if ([] === $movements) {
            throw new EntryEmpty();
        }
        if (!$debit->equals($credit)) {
            throw new EntryUnbalanced($debit, $credit);
        }

        $entry = $this->newEntry($draft->companyId, $draft->date, $draft->sourceType, $draft->sourceId, $draft->sourceNumber, $draft->description, null, $draft->userId);
        foreach ($movements as [$line, $account]) {
            Side::Debit === $line->side
                ? $entry->debit($account->id(), $account->code(), $line->terceroId, $line->amount, $line->description)
                : $entry->credit($account->id(), $account->code(), $line->terceroId, $line->amount, $line->description);
        }
        $entry->close();
        $this->entries->add($entry);

        return $entry->id();
    }

    public function reverse(Uuid $companyId, Uuid $entryId, \DateTimeImmutable $date, Uuid $userId, string $description): Uuid
    {
        $original = $this->entries->get($companyId, $entryId);
        if ($this->entries->isReversed($companyId, $entryId)) {
            throw new EntryAlreadyReversed();
        }
        $this->assertOpen($companyId, $date);

        // The mirror of what was posted, on the same accounts even if one was deactivated since: a void undoes.
        $reversal = $this->newEntry($companyId, $date, $original->sourceType(), $original->sourceId(), $original->sourceNumber(), $description, $original->id(), $userId);
        foreach ($original->lines() as $line) {
            if ($line->debit()->isPositive()) {
                $reversal->credit($line->accountId(), $line->accountCode(), $line->terceroId(), $line->debit(), $line->description());
            }
            if ($line->credit()->isPositive()) {
                $reversal->debit($line->accountId(), $line->accountCode(), $line->terceroId(), $line->credit(), $line->description());
            }
        }
        $reversal->close();
        $this->entries->add($reversal);

        return $reversal->id();
    }

    public function isOpen(Uuid $companyId, \DateTimeImmutable $date): bool
    {
        return $this->settings->of($companyId)->isOpen($date);
    }

    private function assertOpen(Uuid $companyId, \DateTimeImmutable $date): void
    {
        $settings = $this->settings->of($companyId);
        if (!$settings->isOpen($date)) {
            throw new PeriodLocked($settings->lockedUntil() ?? $date);
        }
    }

    private function newEntry(Uuid $companyId, \DateTimeImmutable $date, string $sourceType, Uuid $sourceId, string $sourceNumber, string $description, ?Uuid $reverses, Uuid $userId): JournalEntry
    {
        $number = $this->numbering->journalEntry($companyId)->sequence;

        return new JournalEntry($companyId, $number, $date->setTime(0, 0), $sourceType, $sourceId, $sourceNumber, mb_substr($description, 0, 255), $reverses, $userId, $this->clock->now());
    }

    /**
     * An explicit account, else the tercero's own account for clientes/proveedores (§4.2), else the posting rule.
     */
    private function resolve(Uuid $companyId, EntryLine $line): Account
    {
        if (null !== $line->accountId) {
            return $this->accounts->get($companyId, $line->accountId);
        }
        $concept = $line->concept ?? throw new \LogicException('An entry line names an account or a concept.');

        $override = null === $line->terceroId ? null : match ($concept) {
            PostingConcept::Receivables => $this->terceros->receivableAccountId($companyId, $line->terceroId),
            PostingConcept::Payables => $this->terceros->payableAccountId($companyId, $line->terceroId),
            default => null,
        };
        if (null !== $override) {
            return $this->accounts->get($companyId, $override);
        }

        $rule = $this->rules->forConcept($companyId, $concept) ?? throw new PostingRuleMissing($concept);

        return $this->accounts->get($companyId, $rule->accountId());
    }
}
