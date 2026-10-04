<?php

namespace App\Purchasing\Application\Command;

use App\Ledger\Application\Posting\JournalPoster;
use App\Purchasing\Application\ColombianCalendar;
use App\Purchasing\Domain\Repository\PayableRepository;
use App\Purchasing\Domain\Repository\PurchaseInvoiceRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Clock;

/** The reversing entry dated today (after the fecha de bloqueo), the payables voided, the number kept. */
final class VoidPurchaseInvoiceHandler implements CommandHandler
{
    public function __construct(
        private readonly PurchaseInvoiceRepository $invoices,
        private readonly PayableRepository $payables,
        private readonly JournalPoster $poster,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(VoidPurchaseInvoice $command): void
    {
        $invoice = $this->invoices->lock($command->companyId, $command->invoiceId);
        $invoice->assertVoidable();
        $reason = trim($command->reason);
        $entry = $invoice->journalEntryId() ?? throw new \LogicException('An emitted invoice has its entry.');
        $reversal = $this->poster->reverse($command->companyId, $entry, ColombianCalendar::today($this->clock), $command->userId, \sprintf('Anulación de la factura de compra %s: %s', $invoice->number(), $reason));
        $invoice->void($reason, $command->userId, $this->clock->now(), $reversal);
        foreach ($this->payables->ofInvoice($command->companyId, $invoice->id()) as $payable) {
            $payable->void();
        }
    }
}
