<?php

namespace App\Company\Infrastructure\Persistence;

use App\Company\Domain\Error\CompanyNotFound;
use App\Company\Domain\Model\Company;
use App\Company\Domain\Repository\CompanyRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineCompanyRepository implements CompanyRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $id): Company
    {
        return $this->em->find(Company::class, $id) ?? throw new CompanyNotFound();
    }

    public function identificationTaken(string $identificationNumber, ?Uuid $except = null): bool
    {
        foreach ($this->em->getRepository(Company::class)->findBy(['identificationNumber' => $identificationNumber]) as $company) {
            if (!$company->id()->equals($except)) {
                return true;
            }
        }

        return false;
    }

    public function add(Company $company): void
    {
        $this->em->persist($company);
    }
}
