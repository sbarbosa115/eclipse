<?php

namespace App\Purchasing\Infrastructure\Persistence;

use App\Purchasing\Domain\Error\PurchaseInvoiceNotFound;
use App\Purchasing\Domain\Model\PurchaseInvoice;
use App\Purchasing\Domain\Repository\PurchaseInvoiceRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\Uid\Uuid;

final class DoctrinePurchaseInvoiceRepository implements PurchaseInvoiceRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function lock(Uuid $companyId, Uuid $id): PurchaseInvoice
    {
        $invoice = $this->em->createQueryBuilder()
            ->select('i')->from(PurchaseInvoice::class, 'i')
            ->where('i.companyId = :company')->andWhere('i.id = :id')
            ->setParameter('company', $companyId, 'uuid')->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            // Whatever this request read before, the row as it is now.
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $invoice instanceof PurchaseInvoice ? $invoice : throw new PurchaseInvoiceNotFound();
    }

    public function get(Uuid $companyId, Uuid $id): PurchaseInvoice
    {
        return $this->em->getRepository(PurchaseInvoice::class)->findOneBy(['companyId' => $companyId, 'id' => $id]) ?? throw new PurchaseInvoiceNotFound();
    }

    public function supplierNumberTaken(Uuid $companyId, Uuid $terceroId, string $number, ?Uuid $exceptId = null): bool
    {
        // The column's collation ignores letter case, like the unique index it backs.
        $qb = $this->em->createQueryBuilder()
            ->select('COUNT(i.id)')
            ->from(PurchaseInvoice::class, 'i')
            ->where('i.companyId = :company AND i.terceroId = :tercero AND i.supplierInvoiceNumber = :number')
            ->setParameter('company', $companyId, 'uuid')
            ->setParameter('tercero', $terceroId, 'uuid')
            ->setParameter('number', $number);
        if (null !== $exceptId) {
            $qb->andWhere('i.id <> :except')->setParameter('except', $exceptId, 'uuid');
        }

        return (int) $qb->getQuery()->getSingleScalarResult() > 0;
    }

    public function add(PurchaseInvoice $invoice): void
    {
        $this->em->persist($invoice);
    }

    public function remove(PurchaseInvoice $invoice): void
    {
        $this->em->remove($invoice);
    }
}
