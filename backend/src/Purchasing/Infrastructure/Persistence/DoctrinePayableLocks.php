<?php

namespace App\Purchasing\Infrastructure\Persistence;

use App\Purchasing\Domain\Model\Payable;
use App\Purchasing\Domain\Model\PurchaseInvoice;
use App\Purchasing\Domain\Repository\PayableLocks;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\Uid\Uuid;

final class DoctrinePayableLocks implements PayableLocks
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function lockForPayment(Uuid $companyId, array $ids): array
    {
        if ([] === $ids) {
            return [];
        }

        /** @var list<Payable> $payables */
        $payables = $this->em->createQueryBuilder()
            ->select('p')->from(Payable::class, 'p')
            ->where('p.companyId = :company')->andWhere('p.id IN (:ids)')
            ->setParameter('company', $companyId, 'uuid')
            ->setParameter('ids', array_map(static fn (Uuid $id) => $id->toBinary(), $ids))
            ->orderBy('p.id')
            ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(Query::HINT_REFRESH, true)
            ->getResult();

        $invoices = [];
        foreach ($payables as $payable) {
            $invoices[$payable->invoiceId()->toRfc4122()] = $payable->invoiceId()->toBinary();
        }
        if ([] !== $invoices) {
            $this->em->createQueryBuilder()
                ->select('i')->from(PurchaseInvoice::class, 'i')
                ->where('i.companyId = :company')->andWhere('i.id IN (:ids)')
                ->setParameter('company', $companyId, 'uuid')
                ->setParameter('ids', array_values($invoices))
                ->orderBy('i.id')
                ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(Query::HINT_REFRESH, true)
                ->getResult();
        }

        $byId = [];
        foreach ($payables as $payable) {
            $byId[$payable->id()->toRfc4122()] = $payable;
        }

        return $byId;
    }
}
