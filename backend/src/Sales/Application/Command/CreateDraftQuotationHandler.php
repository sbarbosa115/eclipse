<?php

namespace App\Sales\Application\Command;

use App\Sales\Domain\Repository\QuotationRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Calendar;
use Symfony\Component\Uid\Uuid;

final class CreateDraftQuotationHandler implements CommandHandler
{
    public function __construct(
        private readonly QuotationRepository $quotations,
        private readonly QuotationContent $content,
        private readonly Calendar $calendar,
    ) {
    }

    /**
     * @return Uuid the new draft's id
     *
     * @throws \App\Sales\Domain\Error\InvalidQuotation
     */
    public function __invoke(CreateDraftQuotation $command): Uuid
    {
        $quotation = $this->content->create($command->companyId, $command->userId, $command->data, $this->calendar->now());
        $this->quotations->add($quotation);

        return $quotation->id();
    }
}
