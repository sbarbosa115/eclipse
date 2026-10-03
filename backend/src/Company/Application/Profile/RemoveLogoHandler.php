<?php

namespace App\Company\Application\Profile;

use App\Company\Application\Port\CompanyAudit;
use App\Company\Application\Port\CompanyLogos;
use App\Company\Domain\Error\LogoNotFound;
use App\Company\Domain\Repository\CompanyRepository;
use App\Shared\Application\Command\CommandHandler;

final class RemoveLogoHandler implements CommandHandler
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly CompanyLogos $logos,
        private readonly CompanyAudit $audit,
    ) {
    }

    public function __invoke(RemoveLogo $command): void
    {
        $company = $this->companies->get($command->companyId);
        $previous = $company->logoId() ?? throw new LogoNotFound();
        $company->useLogo(null);
        $this->logos->remove($command->companyId, $previous);
        $this->audit->record($command->companyId, $command->userId, 'company.logo_removed', 'company', $company->id(), ['from' => $previous->toRfc4122()]);
    }
}
