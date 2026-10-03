<?php

namespace App\Company\Application\Profile;

use App\Company\Application\Port\CompanyAudit;
use App\Company\Application\Port\CompanyLogos;
use App\Company\Domain\Repository\CompanyRepository;
use App\Shared\Application\Command\CommandHandler;

final class ReplaceLogoHandler implements CommandHandler
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly CompanyLogos $logos,
        private readonly CompanyAudit $audit,
    ) {
    }

    public function __invoke(ReplaceLogo $command): void
    {
        $company = $this->companies->get($command->companyId);
        $previous = $company->logoId();
        $new = $this->logos->store($command->companyId, $command->userId, $command->originalName, $command->contentType, $command->path);
        $company->useLogo($new);
        if (null !== $previous) {
            $this->logos->remove($command->companyId, $previous);
        }
        $this->audit->record($command->companyId, $command->userId, 'company.logo_changed', 'company', $company->id(), ['from' => $previous?->toRfc4122(), 'to' => $new->toRfc4122(), 'file_name' => $command->originalName]);
    }
}
