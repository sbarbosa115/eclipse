<?php

namespace App\Sales\Application\Command;

use App\Sales\Domain\Repository\QuotationRepository;
use App\Shared\Application\Command\CommandHandler;

final class AcceptQuotationHandler implements CommandHandler
{
    public function __construct(
        private readonly QuotationRepository $quotations,
    ) {
    }

    /** @throws \App\Sales\Domain\Error\QuotationNotOpen */
    public function __invoke(AcceptQuotation $command): void
    {
        $this->quotations->get($command->companyId, $command->quotationId)->accept();
    }
}
