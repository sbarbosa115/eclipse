<?php

namespace App\Company\Infrastructure\Persistence;

use App\Company\Domain\Model\InvoicingResolution;
use App\Company\Domain\Repository\InvoicingResolutionRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineInvoicingResolutionRepository implements InvoicingResolutionRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function current(Uuid $companyId): ?InvoicingResolution
    {
        return $this->query($companyId, null);
    }

    public function lockCurrent(Uuid $companyId): ?InvoicingResolution
    {
        return $this->query($companyId, LockMode::PESSIMISTIC_WRITE);
    }

    public function add(InvoicingResolution $resolution): void
    {
        $this->em->persist($resolution);
    }

    private function query(Uuid $companyId, ?LockMode $lock): ?InvoicingResolution
    {
        $query = $this->em->createQueryBuilder()
            ->select('r')->from(InvoicingResolution::class, 'r')
            ->where('r.companyId = :company')->setParameter('company', $companyId, 'uuid')
            ->orderBy('r.validFrom', 'DESC')->setMaxResults(1)
            ->getQuery();
        if (null !== $lock) {
            $query->setLockMode($lock);
        }
        $resolution = $query->getOneOrNullResult();

        return $resolution instanceof InvoicingResolution ? $resolution : null;
    }
}
