<?php

namespace App\Sales\Application\Command;

use App\Party\Application\Query\TerceroDirectory;
use App\Sales\Domain\Error\DocumentNotEmitted;
use App\Sales\Domain\Error\TerceroHasNoEmail;
use App\Sales\Domain\Event\SalesInvoiceEmailRequested;
use App\Sales\Domain\Repository\SalesInvoiceRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Model\InvoiceStatus;

final class SendSalesInvoiceHandler implements CommandHandler
{
    public function __construct(
        private readonly SalesInvoiceRepository $invoices,
        private readonly TerceroDirectory $terceros,
        private readonly EventBus $events,
    ) {
    }

    public function __invoke(SendSalesInvoice $command): void
    {
        $invoice = $this->invoices->get($command->companyId, $command->invoiceId);
        if (InvoiceStatus::Draft === $invoice->status() || InvoiceStatus::Voided === $invoice->status()) {
            throw new DocumentNotEmitted();
        }
        if (null === $this->terceros->get($command->companyId, $invoice->terceroId())->email) {
            throw new TerceroHasNoEmail();
        }
        $this->events->publish(new SalesInvoiceEmailRequested($command->companyId->toRfc4122(), $invoice->id()->toRfc4122()));
    }
}
