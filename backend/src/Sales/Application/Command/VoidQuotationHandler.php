<?php

namespace App\Sales\Application\Command;

use App\Sales\Domain\Repository\QuotationRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Calendar;

final class VoidQuotationHandler implements CommandHandler
{
    public function __construct(
        private readonly QuotationRepository $quotations,
        private readonly Calendar $calendar,
    ) {
    }

    /**
     * @throws \App\Sales\Domain\Error\QuotationNotOpen
     * @throws \App\Sales\Domain\Error\InvalidQuotation
     */
    public function __invoke(VoidQuotation $command): void
    {
        $this->quotations->get($command->companyId, $command->quotationId)->void($command->reason, $command->userId, $this->calendar->now());
    }
}
