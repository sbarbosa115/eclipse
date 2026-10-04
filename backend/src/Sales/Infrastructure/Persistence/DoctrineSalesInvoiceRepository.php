<?php

namespace App\Sales\Infrastructure\Persistence;

use App\Sales\Domain\Error\SalesInvoiceNotFound;
use App\Sales\Domain\Model\CashReceiptAllocation;
use App\Sales\Domain\Model\SalesInvoice;
use App\Sales\Domain\Repository\SalesInvoiceRepository;
use App\Shared\Domain\Model\ReceiptStatus;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\Uid\Uuid;

final class DoctrineSalesInvoiceRepository implements SalesInvoiceRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function lock(Uuid $companyId, Uuid $id): SalesInvoice
    {
        $invoice = $this->em->createQueryBuilder()
            ->select('i')->from(SalesInvoice::class, 'i')
            ->where('i.companyId = :company')->andWhere('i.id = :id')
            ->setParameter('company', $companyId, 'uuid')->setParameter('id', $id, 'uuid')
            ->getQuery()
            ->setLockMode(LockMode::PESSIMISTIC_WRITE)
            // Whatever this request read before, the row as it is now.
            ->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $invoice instanceof SalesInvoice ? $invoice : throw new SalesInvoiceNotFound();
    }

    public function get(Uuid $companyId, Uuid $id): SalesInvoice
    {
        return $this->em->getRepository(SalesInvoice::class)->findOneBy(['companyId' => $companyId, 'id' => $id]) ?? throw new SalesInvoiceNotFound();
    }

    public function add(SalesInvoice $invoice): void
    {
        $this->em->persist($invoice);
    }

    public function hasAllocations(Uuid $companyId, Uuid $invoiceId): bool
    {
        $count = $this->em->createQueryBuilder()
            ->select('COUNT(a.id)')
            ->from(CashReceiptAllocation::class, 'a')
            ->join('a.receipt', 'r')
            ->where('a.companyId = :company')->andWhere('a.invoiceId = :invoice')->andWhere('r.status <> :voided')
            ->setParameter('company', $companyId, 'uuid')
            ->setParameter('invoice', $invoiceId, 'uuid')
            ->setParameter('voided', ReceiptStatus::Voided)
            ->getQuery()->getSingleScalarResult();

        return (int) $count > 0;
    }
}
