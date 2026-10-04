<?php

namespace App\Purchasing\Application\Command;

use App\Purchasing\Domain\Error\DocumentNotDraft;
use App\Purchasing\Domain\Repository\PurchaseInvoiceRepository;
use App\Shared\Application\Command\CommandHandler;

final class UpdatePurchaseInvoiceHandler implements CommandHandler
{
    public function __construct(
        private readonly PurchaseInvoiceRepository $invoices,
        private readonly PurchaseDraftResolver $resolver,
    ) {
    }

    public function __invoke(UpdatePurchaseInvoice $command): void
    {
        $invoice = $this->invoices->get($command->companyId, $command->invoiceId);
        if (!$invoice->isDraft()) {
            throw new DocumentNotDraft();
        }
        $this->resolver->resolve($command->companyId, $command->contents, $invoice)->applyTo($invoice);
    }
}
