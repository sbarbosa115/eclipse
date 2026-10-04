<?php

namespace App\Company\Application\Query;

use App\Company\Application\Port\CompanyLogos;
use App\Company\Application\Port\LogoFile;
use App\Company\Domain\Error\LogoNotFound;
use App\Company\Domain\Repository\CompanyRepository;
use Symfony\Component\Uid\Uuid;

/** The company's logo file, for the UI and (later) the PDFs. */
final class LogoReader
{
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly CompanyLogos $logos,
    ) {
    }

    /** @throws LogoNotFound */
    public function logo(Uuid $companyId): LogoFile
    {
        $id = $this->companies->get($companyId)->logoId() ?? throw new LogoNotFound();

        return $this->logos->find($companyId, $id) ?? throw new LogoNotFound();
    }
}
