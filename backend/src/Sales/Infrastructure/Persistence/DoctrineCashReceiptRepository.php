<?php

namespace App\Sales\Infrastructure\Persistence;

use App\Sales\Domain\Error\CashReceiptNotFound;
use App\Sales\Domain\Model\CashReceipt;
use App\Sales\Domain\Repository\CashReceiptRepository;
use Doctrine\DBAL\LockMode;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Symfony\Component\Uid\Uuid;

final class DoctrineCashReceiptRepository implements CashReceiptRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $id): CashReceipt
    {
        return $this->em->getRepository(CashReceipt::class)->findOneBy(['companyId' => $companyId, 'id' => $id]) ?? throw new CashReceiptNotFound();
    }

    public function lock(Uuid $companyId, Uuid $id): CashReceipt
    {
        $receipt = $this->em->createQueryBuilder()
            ->select('r')->from(CashReceipt::class, 'r')
            ->where('r.companyId = :company')->andWhere('r.id = :id')
            ->setParameter('company', $companyId, 'uuid')->setParameter('id', $id, 'uuid')
            ->getQuery()->setLockMode(LockMode::PESSIMISTIC_WRITE)->setHint(Query::HINT_REFRESH, true)
            ->getOneOrNullResult();

        return $receipt instanceof CashReceipt ? $receipt : throw new CashReceiptNotFound();
    }

    public function add(CashReceipt $receipt): void
    {
        $this->em->persist($receipt);
    }
}
