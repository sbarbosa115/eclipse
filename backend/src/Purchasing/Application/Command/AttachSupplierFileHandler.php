<?php

namespace App\Purchasing\Application\Command;

use App\Purchasing\Application\Port\SupplierFiles;
use App\Purchasing\Domain\Error\DocumentNotDraft;
use App\Purchasing\Domain\Repository\PurchaseInvoiceRepository;
use App\Shared\Application\Command\CommandHandler;
use Symfony\Component\Uid\Uuid;

final class AttachSupplierFileHandler implements CommandHandler
{
    public function __construct(
        private readonly PurchaseInvoiceRepository $invoices,
        private readonly SupplierFiles $files,
    ) {
    }

    /** @return Uuid the attachment's id */
    public function __invoke(AttachSupplierFile $command): Uuid
    {
        $invoice = $this->invoices->get($command->companyId, $command->invoiceId);
        if (!$invoice->isDraft()) {
            throw new DocumentNotDraft();
        }

        return $this->files->store($command->companyId, $invoice->id(), $command->userId, $command->originalName, $command->contentType, $command->path);
    }
}
