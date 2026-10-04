<?php

namespace App\Purchasing\Application\Command;

use App\Ledger\Application\Posting\JournalPoster;
use App\Purchasing\Application\ColombianCalendar;
use App\Purchasing\Application\Payables\PayableAllocations;
use App\Purchasing\Domain\Model\SupplierPaymentAllocation;
use App\Purchasing\Domain\Repository\PayableLocks;
use App\Purchasing\Domain\Repository\SupplierPaymentRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Clock;

/**
 * Void (§4.12): a payment may be voided at any time, with a reason, dated today (the ledger refuses a date on or before
 * the fecha de bloqueo). Each allocation is given back to its payable and invoice (balance and status), the reversing
 * entry is posted and the number stays used. The payment is locked first, so it is voided, and its money given back,
 * once.
 */
final class VoidSupplierPaymentHandler implements CommandHandler
{
    public function __construct(
        private readonly SupplierPaymentRepository $payments,
        private readonly PayableLocks $locks,
        private readonly PayableAllocations $payables,
        private readonly JournalPoster $poster,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(VoidSupplierPayment $command): void
    {
        $payment = $this->payments->lock($command->companyId, $command->paymentId);
        $today = ColombianCalendar::today($this->clock);
        $payment->void($command->reason, $command->userId, $this->clock->now());

        $this->locks->lockForPayment($command->companyId, array_map(static fn (SupplierPaymentAllocation $a) => $a->openItemId(), $payment->allocations()));
        foreach ($payment->allocations() as $allocation) {
            $this->payables->unapply($command->companyId, $allocation->openItemId(), $allocation->amount());
        }
        $entry = $payment->journalEntryId() ?? throw new \LogicException('An emitted payment has its entry.');
        $payment->recordReversal($this->poster->reverse($command->companyId, $entry, $today, $command->userId, \sprintf('Anulación recibo de pago %s: %s', $payment->number(), $payment->voidReason())));
    }
}
