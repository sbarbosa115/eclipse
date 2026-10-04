<?php

namespace App\Sales\Application\Command;

use App\Sales\Domain\Model\QuotationLine;
use App\Sales\Domain\Repository\QuotationRepository;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Calendar;
use Symfony\Component\Uid\Uuid;

/**
 * Converts a quotation into a draft sales invoice, once (§4.7, §9 Q17): the invoice command is the one that checks
 * the catalogs, so a product, tax or client deactivated since the quotation was made is refused there (422, by field
 * path) and nothing changes. The invoice keeps the quotation as its origin; the quotation becomes accepted and keeps the
 * invoice's id. The invoice is dated today, has the quotation's responsable as vendedor and no formas de pago yet.
 */
final class ConvertQuotationHandler implements CommandHandler
{
    public function __construct(
        private readonly QuotationRepository $quotations,
        private readonly CommandBus $commands,
        private readonly Calendar $calendar,
    ) {
    }

    /**
     * @return Uuid the draft invoice's id
     *
     * @throws \App\Sales\Domain\Error\QuotationAlreadyConverted
     * @throws \App\Sales\Domain\Error\QuotationNotOpen
     * @throws \App\Sales\Domain\Error\InvalidInvoice
     */
    public function __invoke(ConvertQuotation $command): Uuid
    {
        $quotation = $this->quotations->get($command->companyId, $command->quotationId);
        $today = $this->calendar->today();
        $quotation->assertConvertible($today);

        $invoice = $this->commands->dispatch(new CreateDraftSalesInvoice($command->companyId, $command->userId, new SalesInvoiceData(
            $quotation->terceroId(),
            $quotation->contactId(),
            $quotation->responsibleId(),
            $today,
            $quotation->notes(),
            array_map(static fn (QuotationLine $l) => new SalesInvoiceLineData(
                $l->productId(), $l->description(), $l->quantity()->toString(), $l->unitPrice()->toString(), $l->discount()->toString(),
                $l->chargeTax()->taxId(), $l->withholdingTax()->taxId(),
            ), $quotation->lines()),
            [],
        ), $quotation->id()));
        \assert($invoice instanceof Uuid);
        $quotation->convertedTo($invoice, $today);

        return $invoice;
    }
}
