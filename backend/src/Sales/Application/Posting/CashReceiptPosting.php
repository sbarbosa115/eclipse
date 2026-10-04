<?php

namespace App\Sales\Application\Posting;

use App\Ledger\Application\Posting\EntryDraft;
use App\Ledger\Application\Posting\EntryLine;
use App\Ledger\Application\Posting\Side;
use App\Sales\Domain\Model\CashReceipt;
use App\Shared\Domain\Accounting\PostingConcept;
use Symfony\Component\Uid\Uuid;

/**
 * The entry of a recibo de caja, PRD Appendix A.2:
 *
 *     Dr the contado method's account (110505 Caja / 111005 Bancos…), the amount received
 *     Cr 1305 Clientes (tercero), one line per allocation, described by the invoice it pays
 *
 * Clientes is a concept, so the client's own receivable account (§4.2) applies, as on the invoice that opened it.
 * The allocations add up to the amount (CashReceipt::issue), so the entry balances by construction.
 */
final class CashReceiptPosting
{
    public const SOURCE_TYPE = 'cash_receipt';

    public static function entryFor(CashReceipt $receipt, Uuid $userId): EntryDraft
    {
        $client = $receipt->terceroId();
        $lines = [EntryLine::toAccount(Side::Debit, $receipt->amount(), $receipt->accountId(), $client)];
        foreach ($receipt->allocations() as $allocation) {
            $lines[] = EntryLine::toConcept(Side::Credit, $allocation->amount(), PostingConcept::Receivables, $client, $allocation->invoiceNumber());
        }

        return new EntryDraft(
            $receipt->companyId(),
            $receipt->receiptDate(),
            self::SOURCE_TYPE,
            $receipt->id(),
            $receipt->number(),
            \sprintf('Recibo de caja %s · %s', $receipt->number(), $receipt->terceroName()),
            $userId,
            $lines,
        );
    }
}
