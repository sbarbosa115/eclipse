<?php

namespace App\Ledger\Infrastructure\Persistence;

use App\Ledger\Domain\Error\TaxNotFound;
use App\Ledger\Domain\Model\Tax;
use App\Ledger\Domain\Repository\TaxRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineTaxRepository implements TaxRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $id): Tax
    {
        return $this->em->getRepository(Tax::class)->findOneBy(['companyId' => $companyId, 'id' => $id]) ?? throw new TaxNotFound();
    }

    public function add(Tax $tax): void
    {
        $this->em->persist($tax);
    }

    public function remove(Tax $tax): void
    {
        $this->em->remove($tax);
    }

    public function nameTaken(Uuid $companyId, string $name, ?Uuid $except = null): bool
    {
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(t.id)')->from(Tax::class, 't')
            ->where('t.companyId = :company')->setParameter('company', $companyId, 'uuid')
            ->andWhere('LOWER(t.name) = :name')->setParameter('name', mb_strtolower(trim($name)));
        if (null !== $except) {
            $qb->andWhere('t.id <> :except')->setParameter('except', $except, 'uuid');
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }
}
