<?php

namespace App\Sales\Application\Command;

use App\Sales\Domain\Model\InvoiceLineDraft;
use App\Sales\Domain\Model\Quotation;
use App\Sales\Domain\Model\QuotationLine;
use App\Sales\Domain\Repository\QuotationRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Calendar;
use Symfony\Component\Uid\Uuid;

/** A new draft dated today; the offer keeps its validity (the same number of days after the new date). */
final class DuplicateQuotationHandler implements CommandHandler
{
    public function __construct(
        private readonly QuotationRepository $quotations,
        private readonly Calendar $calendar,
    ) {
    }

    /** @return Uuid the new draft's id */
    public function __invoke(DuplicateQuotation $command): Uuid
    {
        $source = $this->quotations->get($command->companyId, $command->quotationId);
        $today = $this->calendar->today();
        $days = max(0, (int) $source->issueDate()->diff($source->expiryDate())->format('%r%a'));

        $copy = new Quotation($command->companyId, $source->terceroId(), $source->terceroName(), $today, $today->modify(\sprintf('+%d days', $days)), $command->userId, $this->calendar->now());
        $copy->revise($source->terceroId(), $source->terceroName(), $source->contactId(), $source->responsibleId(), $today, $today->modify(\sprintf('+%d days', $days)), $source->header(), $source->terms(), $source->notes());
        $copy->replaceLines(array_map(static fn (QuotationLine $l) => new InvoiceLineDraft($l->productId(), $l->description(), $l->quantity(), $l->unitPrice(), $l->discount(), $l->chargeTax(), $l->withholdingTax()), $source->lines()));
        $this->quotations->add($copy);

        return $copy->id();
    }
}
