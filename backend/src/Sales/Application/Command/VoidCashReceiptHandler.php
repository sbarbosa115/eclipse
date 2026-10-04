<?php

namespace App\Sales\Application\Command;

use App\Ledger\Application\Posting\JournalPoster;
use App\Sales\Application\Collection\InvoiceCollections;
use App\Sales\Domain\Error\PeriodLocked;
use App\Sales\Domain\Model\CashReceiptAllocation;
use App\Sales\Domain\Repository\CashReceiptRepository;
use App\Sales\Domain\Repository\ReceivableLocks;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Calendar;

/**
 * Void (§4.12): a receipt may be voided at any time, with a reason, dated today (after the fecha de bloqueo). Each
 * allocation is given back to its invoice (balance and status), the reversing entry is posted and the number stays
 * used. The receipt is locked first, so it is voided, and its money given back, once.
 */
final class VoidCashReceiptHandler implements CommandHandler
{
    public function __construct(
        private readonly CashReceiptRepository $receipts,
        private readonly ReceivableLocks $locks,
        private readonly InvoiceCollections $collections,
        private readonly JournalPoster $poster,
        private readonly Calendar $calendar,
    ) {
    }

    public function __invoke(VoidCashReceipt $command): void
    {
        $receipt = $this->receipts->lock($command->companyId, $command->receiptId);
        $today = $this->calendar->today();
        $receipt->void($command->reason, $command->userId, $this->calendar->now());
        if (!$this->poster->isOpen($command->companyId, $today)) {
            throw new PeriodLocked($today);
        }

        $this->locks->lockForCollection($command->companyId, array_map(static fn (CashReceiptAllocation $a) => $a->openItemId(), $receipt->allocations()));
        foreach ($receipt->allocations() as $allocation) {
            $this->collections->unapply($command->companyId, $allocation->openItemId(), $allocation->amount());
        }
        $entry = $receipt->journalEntryId() ?? throw new \LogicException('An emitted receipt has its entry.');
        $receipt->recordReversal($this->poster->reverse($command->companyId, $entry, $today, $command->userId, \sprintf('Anulación recibo de caja %s: %s', $receipt->number(), $receipt->voidReason())));
    }
}
