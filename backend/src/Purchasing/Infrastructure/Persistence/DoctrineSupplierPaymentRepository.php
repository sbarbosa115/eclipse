<?php

namespace App\Purchasing\Infrastructure\Persistence;

use App\Purchasing\Domain\Error\SupplierPaymentNotFound;
use App\Purchasing\Domain\Model\SupplierPayment;
use App\Purchasing\Domain\Repository\SupplierPaymentRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\Uid\Uuid;

final class DoctrineSupplierPaymentRepository implements SupplierPaymentRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $id): SupplierPayment
    {
        return $this->em->getRepository(SupplierPayment::class)->findOneBy(['companyId' => $companyId, 'id' => $id]) ?? throw new SupplierPaymentNotFound();
    }

    public function lock(Uuid $companyId, Uuid $id): SupplierPayment
    {
        $payment = $this->em->createQueryBuilder()
            ->select('p')->from(SupplierPayment::class, 'p')
            ->where('p.companyId = :company')->andWhere('p.id = :id')
            ->setParameter('company', $companyId, 'uuid')->setParameter('id', $id, 'uuid')
            ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $payment instanceof SupplierPayment ? $payment : throw new SupplierPaymentNotFound();
    }

    public function add(SupplierPayment $payment): void
    {
        $this->em->persist($payment);
    }
}
