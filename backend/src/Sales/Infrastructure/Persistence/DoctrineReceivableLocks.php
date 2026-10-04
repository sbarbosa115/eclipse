<?php

namespace App\Sales\Infrastructure\Persistence;

use App\Sales\Domain\Model\Receivable;
use App\Sales\Domain\Model\SalesInvoice;
use App\Sales\Domain\Repository\ReceivableLocks;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\Uid\Uuid;

final class DoctrineReceivableLocks implements ReceivableLocks
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function lockForCollection(Uuid $companyId, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<Receivable> $receivables */
        $receivables = $this->em->createQueryBuilder()
            ->select('r')->from(Receivable::class, 'r')
            ->where('r.companyId = :company')->andWhere('r.id IN (:ids)')
            ->setParameter('company', $companyId, 'uuid')
            ->setParameter('ids', array_map(static fn (Uuid $id) => $id->toBinary(), $ids))
            ->orderBy('r.id')
            ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(Query::HINT_REFRESH, true)
            ->getResult();

        $invoices = [];
        foreach ($receivables as $receivable) {
            $invoices[$receivable->invoiceId()->toRfc4122()] = $receivable->invoiceId()->toBinary();
        }
        if ([] !== $invoices) {
            $this->em->createQueryBuilder()
                ->select('i')->from(SalesInvoice::class, 'i')
                ->where('i.companyId = :company')->andWhere('i.id IN (:ids)')
                ->setParameter('company', $companyId, 'uuid')
                ->setParameter('ids', array_values($invoices))
                ->orderBy('i.id')
                ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(Query::HINT_REFRESH, true)
                ->getResult();
        }

        $byId = [];
        foreach ($receivables as $receivable) {
            $byId[$receivable->id()->toRfc4122()] = $receivable;
        }

        return $byId;
    }
}
