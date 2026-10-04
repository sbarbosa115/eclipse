<?php

namespace App\Purchasing\Application\Command;

use App\Purchasing\Domain\Repository\PurchaseInvoiceRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Calendar;
use Symfony\Component\Uid\Uuid;

final class DuplicatePurchaseInvoiceHandler implements CommandHandler
{
    public function __construct(
        private readonly PurchaseInvoiceRepository $invoices,
        private readonly Calendar $calendar,
    ) {
    }

    /** @return Uuid the new draft's id */
    public function __invoke(DuplicatePurchaseInvoice $command): Uuid
    {
        $copy = $this->invoices->get($command->companyId, $command->invoiceId)->duplicate($command->userId, $this->calendar->now(), $this->calendar->today());
        $this->invoices->add($copy);

        return $copy->id();
    }
}
