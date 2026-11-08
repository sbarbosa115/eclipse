<?php

namespace App\Purchasing\Application\Command;

use App\Purchasing\Application\Port\SupplierFiles;
use App\Purchasing\Domain\Error\DocumentNotDraft;
use App\Purchasing\Domain\Repository\PurchaseInvoiceRepository;
use App\Shared\Application\Command\CommandHandler;

final class DeletePurchaseInvoiceHandler implements CommandHandler
{
    public function __construct(
        private readonly PurchaseInvoiceRepository $invoices,
        private readonly SupplierFiles $files,
    ) {
    }

    public function __invoke(DeletePurchaseInvoice $command): void
    {
        $invoice = $this->invoices->get($command->companyId, $command->invoiceId);
        if (!$invoice->isDraft()) {
            throw new DocumentNotDraft();
        }
        $corrected = $invoice->correctedFromId();
        if (null !== $corrected) {
            // The voided invoice it corrected stops pointing at a draft that is gone.
            $this->invoices->get($command->companyId, $corrected)->correctionDiscarded($invoice->id());
        }
        $this->files->removeAll($command->companyId, $invoice->id());
        $this->invoices->remove($invoice);
    }
}
