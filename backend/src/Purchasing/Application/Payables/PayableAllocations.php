<?php

namespace App\Purchasing\Application\Payables;

use App\Purchasing\Domain\Repository\PayableRepository;
use App\Purchasing\Domain\Repository\PurchaseInvoiceRepository;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * How a supplier payment (§4.11, item 12) pays purchase invoices without touching their aggregate: call it from the
 * payment's own command handler (same transaction). The payable's balance and its invoice's status
 * (partially_paid / paid / emitted again) follow.
 */
final class PayableAllocations
{
    public function __construct(
        private readonly PayableRepository $payables,
        private readonly PurchaseInvoiceRepository $invoices,
    ) {
    }

    /**
     * @throws \App\Purchasing\Domain\Error\PayableNotFound          another company's payable too
     * @throws \App\Purchasing\Domain\Error\AllocationExceedsBalance more than its balance
     * @throws \App\Purchasing\Domain\Error\PayableVoided            its invoice was voided
     */
    public function apply(Uuid $companyId, Uuid $payableId, Money $amount): void
    {
        $payable = $this->payables->get($companyId, $payableId);
        $payable->allocate($amount);
        $this->invoices->get($companyId, $payable->invoiceId())->applyPayment($amount);
    }

    /** A supplier payment that paid $amount of the payable was voided. */
    public function unapply(Uuid $companyId, Uuid $payableId, Money $amount): void
    {
        $payable = $this->payables->get($companyId, $payableId);
        $payable->release($amount);
        $this->invoices->get($companyId, $payable->invoiceId())->unapplyPayment($amount);
    }
}
