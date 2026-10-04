<?php

namespace App\Sales\Application\Command;

use App\Company\Application\Numbering\Numbering;
use App\Party\Application\Query\TerceroDirectory;
use App\Sales\Domain\Error\TerceroHasNoEmail;
use App\Sales\Domain\Error\TerceroInactive;
use App\Sales\Domain\Event\QuotationEmailRequested;
use App\Sales\Domain\Repository\QuotationRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Calendar;

/**
 * Emission of a cotización (§4.7), in one transaction: the draft's own checks, an active client, the next number of
 * series C. It posts **nothing** (no JournalPoster here, on purpose) and never touches cartera.
 */
final class EmitQuotationHandler implements CommandHandler
{
    public function __construct(
        private readonly QuotationRepository $quotations,
        private readonly TerceroDirectory $terceros,
        private readonly Numbering $numbering,
        private readonly EventBus $events,
        private readonly Calendar $calendar,
    ) {
    }

    public function __invoke(EmitQuotation $command): void
    {
        $quotation = $this->quotations->get($command->companyId, $command->quotationId);
        $today = $this->calendar->today();
        $quotation->assertEmittable($today);

        $client = $this->terceros->get($command->companyId, $quotation->terceroId());
        if (!$client->active) {
            throw new TerceroInactive();
        }
        if ($command->send && null === $client->email) {
            throw new TerceroHasNoEmail();
        }

        $number = $this->numbering->quotation($command->companyId);
        $quotation->emit($number->prefix, $number->sequence, $today, $command->userId, $this->calendar->now());

        if ($command->send) {
            $this->events->publish(new QuotationEmailRequested($command->companyId->toRfc4122(), $quotation->id()->toRfc4122()));
        }
    }
}
