<?php

namespace App\Company\Application\Profile;

use App\Company\Application\Port\CompanyAudit;
use App\Company\Domain\Repository\CompanyRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Clock;

final class ConfirmManualInvoicingHandler implements CommandHandler
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly CompanyAudit $audit,
        private readonly Clock $clock,
    ) {
    }

    public function __invoke(ConfirmManualInvoicing $command): void
    {
        $company = $this->companies->get($command->companyId);
        $at = $this->clock->now();
        $company->confirmManualInvoicing($command->userId, $at);
        $this->audit->record($command->companyId, $command->userId, 'company.manual_invoicing_confirmed', 'company', $company->id(), ['confirmed_at' => $at->format(\DATE_ATOM)]);
    }
}
