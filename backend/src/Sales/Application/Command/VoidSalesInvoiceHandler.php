<?php

namespace App\Sales\Application\Command;

use App\Ledger\Application\Posting\JournalPoster;
use App\Sales\Domain\Error\PeriodLocked;
use App\Sales\Domain\Repository\ReceivableRepository;
use App\Sales\Domain\Repository\SalesInvoiceRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Calendar;

/**
 * Void (§4.12): while no receipt is applied, dated today (after the fecha de bloqueo), with a reason; the receivables
 * leave the cartera and the reversing entry is posted. The number stays used.
 */
final class VoidSalesInvoiceHandler implements CommandHandler
{
    public function __construct(
        private readonly SalesInvoiceRepository $invoices,
        private readonly ReceivableRepository $receivables,
        private readonly JournalPoster $poster,
        private readonly Calendar $calendar,
    ) {
    }

    public function __invoke(VoidSalesInvoice $command): void
    {
        $invoice = $this->invoices->lock($command->companyId, $command->invoiceId);
        $today = $this->calendar->today();
        $invoice->void($command->reason, $this->invoices->hasAllocations($command->companyId, $invoice->id()), $command->userId, $this->calendar->now());
        if (!$this->poster->isOpen($command->companyId, $today)) {
            throw new PeriodLocked($today);
        }

        foreach ($this->receivables->ofInvoice($command->companyId, $invoice->id()) as $receivable) {
            $receivable->void();
        }
        $entry = $invoice->journalEntryId() ?? throw new \LogicException('An emitted invoice has its entry.');
        $reversal = $this->poster->reverse($command->companyId, $entry, $today, $command->userId, \sprintf('Anulación factura de venta %s: %s', $invoice->number(), $invoice->voidReason()));
        $invoice->recordReversal($reversal);
    }
}
