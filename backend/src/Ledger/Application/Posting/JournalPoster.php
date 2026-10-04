<?php

namespace App\Ledger\Application\Posting;

use Symfony\Component\Uid\Uuid;

/**
 * The one way into the books (principle 1: one ledger, many sources). A document's emit or void handler calls it in
 * its own transaction, so a refusal here undoes the emission.
 *
 * post() resolves each line's concept to an account (the tercero's override for clientes/proveedores, else the posting
 * rule), refuses a non-postable or inactive account, merges nothing (one journal line per entry line, zero lines
 * skipped), and refuses an entry that does not balance to the cent (entry_unbalanced) or is dated on or before the
 * fecha de bloqueo (period_locked).
 *
 * reverse() posts the mirror of an entry (débitos and créditos swapped), dated $date, which must also be after the lock
 * date; the original is never touched.
 *
 * Contract fixed by item 0; implemented by the "ledger" item.
 */
interface JournalPoster
{
    /**
     * @return Uuid the new entry's id
     *
     * @throws \App\Shared\Domain\Error\DomainError entry_unbalanced, period_locked, account_not_postable, posting_rule_missing
     */
    public function post(EntryDraft $draft): Uuid;

    /**
     * @return Uuid the reversing entry's id
     *
     * @throws \App\Shared\Domain\Error\DomainError period_locked
     */
    public function reverse(Uuid $companyId, Uuid $entryId, \DateTimeImmutable $date, Uuid $userId, string $description): Uuid;

    /** Whether a document may carry this date (after the fecha de bloqueo), to refuse before building anything. */
    public function isOpen(Uuid $companyId, \DateTimeImmutable $date): bool;
}
