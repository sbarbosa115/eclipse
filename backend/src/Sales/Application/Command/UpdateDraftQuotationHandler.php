<?php

namespace App\Sales\Application\Command;

use App\Sales\Domain\Error\DocumentNotDraft;
use App\Sales\Domain\Model\QuotationStatus;
use App\Sales\Domain\Repository\QuotationRepository;
use App\Shared\Application\Command\CommandHandler;

final class UpdateDraftQuotationHandler implements CommandHandler
{
    public function __construct(
        private readonly QuotationRepository $quotations,
        private readonly QuotationContent $content,
    ) {
    }

    /**
     * @throws DocumentNotDraft
     * @throws \App\Sales\Domain\Error\InvalidQuotation
     */
    public function __invoke(UpdateDraftQuotation $command): void
    {
        $quotation = $this->quotations->get($command->companyId, $command->quotationId);
        if (QuotationStatus::Draft !== $quotation->status()) {
            throw new DocumentNotDraft();
        }
        $this->content->write($quotation, $command->data);
    }
}
