<?php

namespace App\Purchasing\Application\Posting;

use App\Ledger\Application\Posting\EntryDraft;
use App\Ledger\Application\Posting\EntryLine;
use App\Ledger\Application\Posting\Side;
use App\Purchasing\Domain\Model\SupplierPayment;
use App\Shared\Domain\Accounting\PostingConcept;
use Symfony\Component\Uid\Uuid;

/**
 * The entry of a recibo de pago, PRD Appendix A.4:
 *
 *     Dr 2205 Proveedores (tercero), one line per allocation, described by the invoice it pays
 *     Cr the contado method's account (110505 Caja / 111005 Bancos…), the amount paid
 *
 * Proveedores is a concept, so the supplier's own payable account (§4.2) applies, as on the invoice that opened it.
 * The allocations add up to the amount (SupplierPayment::issue), so the entry balances by construction.
 */
final class SupplierPaymentPosting
{
    public const SOURCE_TYPE = 'supplier_payment';

    public static function entryFor(SupplierPayment $payment, Uuid $userId): EntryDraft
    {
        $supplier = $payment->terceroId();
        $lines = [];
        foreach ($payment->allocations() as $allocation) {
            $lines[] = EntryLine::toConcept(Side::Debit, $allocation->amount(), PostingConcept::Payables, $supplier, $allocation->invoiceNumber());
        }
        $lines[] = EntryLine::toAccount(Side::Credit, $payment->amount(), $payment->accountId(), $supplier);

        return new EntryDraft(
            $payment->companyId(),
            $payment->receiptDate(),
            self::SOURCE_TYPE,
            $payment->id(),
            $payment->number(),
            \sprintf('Recibo de pago %s · %s', $payment->number(), $payment->terceroName()),
            $userId,
            $lines,
        );
    }
}
