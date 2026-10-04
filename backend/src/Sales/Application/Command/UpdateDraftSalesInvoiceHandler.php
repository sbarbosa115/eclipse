<?php

namespace App\Sales\Application\Command;

use App\Sales\Domain\Error\DocumentNotDraft;
use App\Sales\Domain\Repository\SalesInvoiceRepository;
use App\Shared\Application\Command\CommandHandler;

final class UpdateDraftSalesInvoiceHandler implements CommandHandler
{
    public function __construct(
        private readonly SalesInvoiceRepository $invoices,
        private readonly SalesInvoiceContent $content,
    ) {
    }

    /**
     * @throws DocumentNotDraft
     * @throws \App\Sales\Domain\Error\InvalidInvoice
     */
    public function __invoke(UpdateDraftSalesInvoice $command): void
    {
        $invoice = $this->invoices->get($command->companyId, $command->invoiceId);
        if (!$invoice->status()->isEditable()) {
            throw new DocumentNotDraft();
        }
        $this->content->write($invoice, $command->data);
    }
}
