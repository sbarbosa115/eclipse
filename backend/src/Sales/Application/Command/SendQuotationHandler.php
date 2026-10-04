<?php

namespace App\Sales\Application\Command;

use App\Party\Application\Query\TerceroDirectory;
use App\Sales\Domain\Error\QuotationNotOpen;
use App\Sales\Domain\Error\TerceroHasNoEmail;
use App\Sales\Domain\Event\QuotationEmailRequested;
use App\Sales\Domain\Model\QuotationStatus;
use App\Sales\Domain\Repository\QuotationRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Calendar;

final class SendQuotationHandler implements CommandHandler
{
    public function __construct(
        private readonly QuotationRepository $quotations,
        private readonly TerceroDirectory $terceros,
        private readonly EventBus $events,
        private readonly Calendar $calendar,
    ) {
    }

    public function __invoke(SendQuotation $command): void
    {
        $quotation = $this->quotations->get($command->companyId, $command->quotationId);
        if (QuotationStatus::Emitted !== $quotation->statusOn($this->calendar->today())) {
            throw new QuotationNotOpen();
        }
        if (null === $this->terceros->get($command->companyId, $quotation->terceroId())->email) {
            throw new TerceroHasNoEmail();
        }
        $this->events->publish(new QuotationEmailRequested($command->companyId->toRfc4122(), $quotation->id()->toRfc4122()));
    }
}
