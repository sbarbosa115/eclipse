<?php

namespace App\Company\Application;

use App\Company\Domain\Error\IdentificationTaken;
use App\Company\Domain\Model\Company;
use App\Company\Domain\Repository\CompanyRepository;
use App\Shared\Application\Company\CompanyProvisioner;
use App\Shared\Domain\Fiscal\CheckDigit;
use App\Shared\Domain\Fiscal\IdentificationType;
use Symfony\Component\Uid\Uuid;

/**
 * Creates a company and has every context provision it (the PUC chart, posting rules, taxes, payment methods,
 * numbering series), all inside the caller's transaction: Access's sign-up is the caller.
 */
final class CompanyRegistration
{
    /**
     * @param iterable<CompanyProvisioner> $provisioners highest priority first
     */
    public function __construct(
        private readonly CompanyRepository $companies,
        private readonly iterable $provisioners,
    ) {
    }

    public function register(string $legalName, string $nit, \DateTimeImmutable $at): Uuid
    {
        $digits = preg_replace('/\D/', '', $nit) ?? '';
        if ($this->companies->identificationTaken($digits)) {
            throw new IdentificationTaken();
        }

        $company = new Company(trim($legalName), IdentificationType::Nit, $digits, CheckDigit::of($digits), $at);
        $this->companies->add($company);
        foreach ($this->provisioners as $provisioner) {
            $provisioner->provision($company->id());
        }

        return $company->id();
    }
}
