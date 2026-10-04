<?php

namespace App\Sales\Infrastructure\Persistence;

use App\Sales\Application\Query\QuotationFilter;
use App\Sales\Application\Query\QuotationPage;
use App\Sales\Application\Query\QuotationQueries;
use App\Sales\Domain\Model\Quotation;
use App\Sales\Domain\Model\QuotationStatus;
use App\Sales\Domain\Repository\QuotationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrineQuotationQueries implements QuotationQueries
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly QuotationRepository $quotations,
    ) {
    }

    public function search(Uuid $companyId, QuotationFilter $filter, \DateTimeImmutable $today): QuotationPage
    {
        $page = max(1, $filter->page);
        $perPage = max(1, min(100, $filter->perPage));

        $qb = $this->em->createQueryBuilder()->from(Quotation::class, 'q')
            ->where('q.companyId = :company')->setParameter('company', $companyId, 'uuid');
        $query = trim((string) $filter->query);
        if ('' !== $query) {
            $qb->andWhere('q.number LIKE :q OR q.terceroName LIKE :q')->setParameter('q', '%'.addcslashes($query, '%_\\').'%');
        }
        if (null !== $filter->status && null !== $status = QuotationStatus::tryFrom($filter->status)) {
            // Expired is never stored: it is an emitted quotation whose fecha de vencimiento has passed.
            match ($status) {
                QuotationStatus::Expired => $qb->andWhere('q.status = :status AND q.expiryDate < :today')->setParameter('status', QuotationStatus::Emitted),
                QuotationStatus::Emitted => $qb->andWhere('q.status = :status AND q.expiryDate >= :today')->setParameter('status', QuotationStatus::Emitted),
                default => $qb->andWhere('q.status = :status')->setParameter('status', $status),
            };
            if (\in_array($status, [QuotationStatus::Expired, QuotationStatus::Emitted], true)) {
                $qb->setParameter('today', $today->setTime(0, 0), 'date_immutable');
            }
        }
        if (null !== $filter->from) {
            $qb->andWhere('q.issueDate >= :from')->setParameter('from', $filter->from->setTime(0, 0), 'date_immutable');
        }
        if (null !== $filter->to) {
            $qb->andWhere('q.issueDate <= :to')->setParameter('to', $filter->to->setTime(0, 0), 'date_immutable');
        }
        if (null !== $filter->terceroId && Uuid::isValid($filter->terceroId)) {
            $qb->andWhere('q.terceroId = :tercero')->setParameter('tercero', Uuid::fromString($filter->terceroId), 'uuid');
        }

        $total = (int) (clone $qb)->select('COUNT(q.id)')->getQuery()->getSingleScalarResult();
        $ids = array_column((clone $qb)->select('q.id')
            ->orderBy('q.issueDate', 'DESC')->addOrderBy('q.sequence', 'DESC')->addOrderBy('q.createdAt', 'DESC')
            ->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage)->getQuery()->getArrayResult(), 'id');
        if ([] === $ids) {
            return new QuotationPage([], $total, $page, $perPage);
        }

        /** @var list<Quotation> $rows */
        $rows = $this->em->createQueryBuilder()->select('q')->from(Quotation::class, 'q')
            ->where('q.id IN (:ids)')->setParameter('ids', array_map(static fn (Uuid $id) => $id->toBinary(), $ids))
            ->getQuery()->getResult();
        $byId = [];
        foreach ($rows as $row) {
            $byId[$row->id()->toRfc4122()] = $row;
        }
        $items = [];
        foreach ($ids as $id) {
            $items[] = $byId[$id->toRfc4122()];
        }

        return new QuotationPage($items, $total, $page, $perPage);
    }

    public function get(Uuid $companyId, Uuid $id): Quotation
    {
        return $this->quotations->get($companyId, $id);
    }
}
