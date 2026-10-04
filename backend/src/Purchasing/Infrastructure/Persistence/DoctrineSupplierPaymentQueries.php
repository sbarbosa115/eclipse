<?php

namespace App\Purchasing\Infrastructure\Persistence;

use App\Purchasing\Application\Query\SupplierPaymentFilter;
use App\Purchasing\Application\Query\SupplierPaymentPage;
use App\Purchasing\Application\Query\SupplierPaymentQueries;
use App\Purchasing\Domain\Model\SupplierPayment;
use App\Purchasing\Domain\Repository\SupplierPaymentRepository;
use App\Shared\Domain\Model\ReceiptStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineSupplierPaymentQueries implements SupplierPaymentQueries
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SupplierPaymentRepository $payments,
    ) {
    }

    public function search(Uuid $companyId, SupplierPaymentFilter $filter): SupplierPaymentPage
    {
        $page = max(1, $filter->page);
        $perPage = max(1, min(100, $filter->perPage));

        $qb = $this->em->createQueryBuilder()->from(SupplierPayment::class, 'r')
            ->where('r.companyId = :company')->setParameter('company', $companyId, 'uuid');
        $query = trim((string) $filter->query);
        if ('' !== $query) {
            $qb->andWhere('r.number LIKE :q OR r.terceroName LIKE :q')->setParameter('q', '%'.addcslashes($query, '%_\\').'%');
        }
        if (null !== $filter->status && null !== $status = ReceiptStatus::tryFrom($filter->status)) {
            $qb->andWhere('r.status = :status')->setParameter('status', $status);
        }
        if (null !== $filter->from) {
            $qb->andWhere('r.receiptDate >= :from')->setParameter('from', $filter->from->setTime(0, 0), 'date_immutable');
        }
        if (null !== $filter->to) {
            $qb->andWhere('r.receiptDate <= :to')->setParameter('to', $filter->to->setTime(0, 0), 'date_immutable');
        }
        if (null !== $filter->terceroId && Uuid::isValid($filter->terceroId)) {
            $qb->andWhere('r.terceroId = :tercero')->setParameter('tercero', Uuid::fromString($filter->terceroId), 'uuid');
        }

        $total = (int) (clone $qb)->select('COUNT(r.id)')->getQuery()->getSingleScalarResult();
        // The page's ids first, then the rows with their allocations in one query (the list shows the invoices paid).
        $ids = array_column((clone $qb)->select('r.id')
            ->orderBy('r.receiptDate', 'DESC')->addOrderBy('r.sequence', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getArrayResult(), 'id');
        if ([] === $ids) {
            return new SupplierPaymentPage([], $total, $page, $perPage);
        }

        /** @var list<SupplierPayment> $rows */
        $rows = $this->em->createQueryBuilder()->select('r', 'a')->from(SupplierPayment::class, 'r')
            ->leftJoin('r.allocations', 'a')
            ->where('r.id IN (:ids)')->setParameter('ids', array_map(static fn (Uuid $id) => $id->toBinary(), $ids))
            ->getQuery()->getResult();
        $byId = [];
        foreach ($rows as $row) {
            $byId[$row->id()->toRfc4122()] = $row;
        }
        $items = [];
        foreach ($ids as $id) {
            $items[] = $byId[$id->toRfc4122()];
        }

        return new SupplierPaymentPage($items, $total, $page, $perPage);
    }

    public function get(Uuid $companyId, Uuid $id): SupplierPayment
    {
        return $this->payments->get($companyId, $id);
    }
}
