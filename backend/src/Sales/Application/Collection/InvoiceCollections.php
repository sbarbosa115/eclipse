<?php

namespace App\Sales\Application\Collection;

use App\Sales\Domain\Repository\ReceivableRepository;
use App\Sales\Domain\Repository\SalesInvoiceRepository;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * How a recibo de caja (the "cash-receipt" item) collects an invoice, without touching the invoice's aggregate: call
 * it from the receipt's own emit and void handlers, inside their transaction (the command bus flushes).
 *
 * apply() lowers the receivable's balance and moves the invoice to partially_paid, or paid once its crédito part is
 * collected (AC-4); unapply() gives the amount back when the receipt is voided. A receivable of another company is
 * receivable_not_found; more than the balance is allocation_exceeds_balance; a draft or voided invoice is
 * document_not_emitted.
 */
final class InvoiceCollections
{
    public function __construct(
        private readonly ReceivableRepository $receivables,
        private readonly SalesInvoiceRepository $invoices,
    ) {
    }

    /**
     * @return Uuid the invoice the receivable belongs to (what an allocation records)
     *
     * @throws \App\Sales\Domain\Error\ReceivableNotFound
     * @throws \App\Sales\Domain\Error\AllocationExceedsBalance
     * @throws \App\Sales\Domain\Error\DocumentNotEmitted
     */
    public function apply(Uuid $companyId, Uuid $receivableId, Money $amount): Uuid
    {
        $receivable = $this->receivables->get($companyId, $receivableId);
        $invoice = $this->invoices->get($companyId, $receivable->invoiceId());
        $invoice->applyPayment($amount);
        $receivable->apply($amount);

        return $invoice->id();
    }

    /**
     * @throws \App\Sales\Domain\Error\ReceivableNotFound
     */
    public function unapply(Uuid $companyId, Uuid $receivableId, Money $amount): void
    {
        $receivable = $this->receivables->get($companyId, $receivableId);
        $this->invoices->get($companyId, $receivable->invoiceId())->revertPayment($amount);
        $receivable->unapply($amount);
    }
}
