<?php

namespace App\Company\Infrastructure\Persistence;

use App\Company\Domain\Model\NumberingSeries;
use App\Company\Domain\Model\SeriesKind;
use App\Company\Domain\Repository\NumberingSeriesRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineNumberingSeriesRepository implements NumberingSeriesRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function lock(Uuid $companyId, SeriesKind $kind): NumberingSeries
    {
        $series = $this->em->createQueryBuilder()
            ->select('s')->from(NumberingSeries::class, 's')
            ->where('s.companyId = :company')->andWhere('s.kind = :kind')
            ->setParameter('company', $companyId, 'uuid')->setParameter('kind', $kind)
            ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->getOneOrNullResult();
        if ($series instanceof NumberingSeries) {
            return $series;
        }

        // A company provisioned before this kind existed gets it now, from its default prefix.
        $series = new NumberingSeries($companyId, $kind, $kind->defaultPrefix());
        $this->em->persist($series);

        return $series;
    }

    public function add(NumberingSeries $series): void
    {
        $this->em->persist($series);
    }
}
