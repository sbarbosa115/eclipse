<?php

namespace App\Company\Domain\Repository;

use App\Company\Domain\Model\Company;
use Symfony\Component\Uid\Uuid;

interface CompanyRepository
{
    /** @throws \App\Company\Domain\Error\CompanyNotFound */
    public function get(Uuid $id): Company;

    /** Whether another company (not $except) is registered under this identification number. */
    public function identificationTaken(string $identificationNumber, ?Uuid $except = null): bool;

    public function add(Company $company): void;
}
