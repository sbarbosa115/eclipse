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

    public function identificationTaken(string $identificationNumber): bool
    {
        return null !== $this->em->getRepository(Company::class)->findOneBy(['identificationNumber' => $identificationNumber]);
    }

    public function add(Company $company): void
    {
        $this->em->persist($company);
    }
}
