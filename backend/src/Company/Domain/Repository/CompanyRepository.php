<?php

namespace App\Company\Domain\Repository;

use App\Company\Domain\Model\Company;
use Symfony\Component\Uid\Uuid;

interface CompanyRepository
{
    /** @throws \App\Company\Domain\Error\CompanyNotFound */
    public function get(Uuid $id): Company;

    public function identificationTaken(string $identificationNumber): bool;

    public function add(Company $company): void;
}
