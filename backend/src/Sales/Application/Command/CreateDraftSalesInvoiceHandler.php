<?php

namespace App\Sales\Application\Command;

use App\Sales\Domain\Model\SalesInvoice;
use App\Sales\Domain\Repository\SalesInvoiceRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Calendar;
use Symfony\Component\Uid\Uuid;

final class CreateDraftSalesInvoiceHandler implements CommandHandler
{
    public function __construct(
        private readonly SalesInvoiceRepository $invoices,
        private readonly SalesInvoiceContent $content,
        private readonly Calendar $calendar,
    ) {
    }

    /**
     * @return Uuid the new draft's id
     *
     * @throws \App\Sales\Domain\Error\InvalidInvoice
     */
    public function __invoke(CreateDraftSalesInvoice $command): Uuid
    {
        $data = $command->data;
        $name = $this->content->clientName($command->companyId, $data->terceroId) ?? '';
        $invoice = new SalesInvoice($command->companyId, $data->terceroId, $name, $data->issueDate, $command->userId, $this->calendar->now());
        $this->content->write($invoice, $data);
        if (null !== $command->quotationId) {
            $invoice->originatesFrom($command->quotationId);
        }
        $this->invoices->add($invoice);

        return $invoice->id();
    }
}
