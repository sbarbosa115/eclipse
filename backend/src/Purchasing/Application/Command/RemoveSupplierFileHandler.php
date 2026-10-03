<?php

namespace App\Purchasing\Application\Command;

use App\Purchasing\Application\Port\SupplierFiles;
use App\Purchasing\Domain\Error\DocumentNotDraft;
use App\Purchasing\Domain\Repository\PurchaseInvoiceRepository;
use App\Shared\Application\Command\CommandHandler;

final class RemoveSupplierFileHandler implements CommandHandler
{
    public function __construct(
        private readonly PurchaseInvoiceRepository $invoices,
        private readonly SupplierFiles $files,
    ) {
    }

    public function __invoke(RemoveSupplierFile $command): void
    {
        $invoice = $this->invoices->get($command->companyId, $command->invoiceId);
        $this->files->find($command->companyId, $invoice->id(), $command->attachmentId);
        if (!$invoice->isDraft()) {
            throw new DocumentNotDraft();
        }
        $this->files->remove($command->companyId, $invoice->id(), $command->attachmentId);
    }
}
