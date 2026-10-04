<?php

namespace App\Company\Application\Resolution;

use App\Company\Domain\Repository\CompanyRepository;
use App\Shared\Application\Audit\AuditTrail;
use App\Shared\Application\Command\CommandHandler;

final class UpdateResolutionWarningsHandler implements CommandHandler
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly AuditTrail $audit,
    ) {
    }

    public function __invoke(UpdateResolutionWarnings $command): void
    {
        $company = $this->companies->get($command->companyId);
        $before = ['numbers' => $company->resolutionWarningNumbers(), 'days' => $company->resolutionWarningDays()];
        $company->warnResolutionAt($command->numbers, $command->days);
        $this->audit->record($command->companyId, $command->userId, 'company.resolution_warnings_updated', 'company', $company->id(), ['from' => $before, 'to' => ['numbers' => $command->numbers, 'days' => $command->days]]);
    }
}
