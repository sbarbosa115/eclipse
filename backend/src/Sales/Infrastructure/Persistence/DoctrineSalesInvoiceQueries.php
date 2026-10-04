<?php

namespace App\Sales\Infrastructure\Persistence;

use App\Sales\Application\Query\SalesInvoiceFilter;
use App\Sales\Application\Query\SalesInvoicePage;
use App\Sales\Application\Query\SalesInvoiceQueries;
use App\Sales\Domain\Model\SalesInvoice;
use App\Sales\Domain\Repository\ReceivableRepository;
use App\Sales\Domain\Repository\SalesInvoiceRepository;
use App\Shared\Domain\Model\InvoiceStatus;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineSalesInvoiceQueries implements SalesInvoiceQueries
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly SalesInvoiceRepository $invoices,
        private readonly ReceivableRepository $receivables,
    ) {
    }

    public function search(Uuid $companyId, SalesInvoiceFilter $filter): SalesInvoicePage
    {
        $page = max(1, $filter->page);
        $perPage = max(1, min(100, $filter->perPage));

        $qb = $this->em->createQueryBuilder()->from(SalesInvoice::class, 'i')
            ->where('i.companyId = :company')->setParameter('company', $companyId, 'uuid');
        $query = trim((string) $filter->query);
        if ('' !== $query) {
            $qb->andWhere('i.number LIKE :q OR i.terceroName LIKE :q')->setParameter('q', '%'.addcslashes($query, '%_\\').'%');
        }
        if (null !== $filter->status && null !== $status = InvoiceStatus::tryFrom($filter->status)) {
            $qb->andWhere('i.status = :status')->setParameter('status', $status);
        }
        if (null !== $filter->from) {
            $qb->andWhere('i.issueDate >= :from')->setParameter('from', $filter->from->setTime(0, 0), 'date_immutable');
        }
        if (null !== $filter->to) {
            $qb->andWhere('i.issueDate <= :to')->setParameter('to', $filter->to->setTime(0, 0), 'date_immutable');
        }
        if (null !== $filter->terceroId && Uuid::isValid($filter->terceroId)) {
            $qb->andWhere('i.terceroId = :tercero')->setParameter('tercero', Uuid::fromString($filter->terceroId), 'uuid');
        }

        $total = (int) (clone $qb)->select('COUNT(i.id)')->getQuery()->getSingleScalarResult();
        // The rows only: the list reads the payments for the balance, fetched in one more query below.
        $ids = array_column((clone $qb)->select('i.id')
            ->orderBy('i.issueDate', 'DESC')->addOrderBy('i.internalNumber', 'DESC')->addOrderBy('i.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getArrayResult(), 'id');
        if ([] === $ids) {
            return new SalesInvoicePage([], $total, $page, $perPage);
        }

        /** @var list<SalesInvoice> $rows */
        $rows = $this->em->createQueryBuilder()->select('i', 'p')->from(SalesInvoice::class, 'i')
            ->leftJoin('i.payments', 'p')
            ->where('i.id IN (:ids)')->setParameter('ids', array_map(static fn (Uuid $id) => $id->toBinary(), $ids))
            ->getQuery()->getResult();
        $byId = [];
        foreach ($rows as $row) {
            $byId[$row->id()->toRfc4122()] = $row;
        }
        $items = [];
        foreach ($ids as $id) {
            $items[] = $byId[$id->toRfc4122()];
        }

        return new SalesInvoicePage($items, $total, $page, $perPage);
    }

    public function get(Uuid $companyId, Uuid $id): SalesInvoice
    {
        return $this->invoices->get($companyId, $id);
    }

    public function receivables(Uuid $companyId, Uuid $invoiceId): array
    {
        return $this->receivables->ofInvoice($companyId, $invoiceId);
    }
}
