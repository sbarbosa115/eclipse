<?php

namespace App\Purchasing\Application\Command;

use App\Purchasing\Domain\Model\PurchaseInvoice;
use App\Purchasing\Domain\Repository\PurchaseInvoiceRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Clock;
use Symfony\Component\Uid\Uuid;

final class CreatePurchaseInvoiceHandler implements CommandHandler
{
    public function __construct(
        private readonly PurchaseInvoiceRepository $invoices,
        private readonly PurchaseDraftResolver $resolver,
        private readonly Clock $clock,
    ) {
    }

    /** @return Uuid the draft's id */
    public function __invoke(CreatePurchaseInvoice $command): Uuid
    {
        $draft = $this->resolver->resolve($command->companyId, $command->contents);
        $invoice = new PurchaseInvoice($command->companyId, $draft->terceroId, $draft->terceroName, $draft->supplierInvoiceNumber, $draft->issueDate, $command->userId, $this->clock->now());
        $draft->applyTo($invoice);
        $this->invoices->add($invoice);

        return $invoice->id();
    }
}
