<?php

namespace App\Purchasing\Infrastructure\Persistence;

use App\Purchasing\Domain\Error\PurchaseOrderNotFound;
use App\Purchasing\Domain\Model\PurchaseOrder;
use App\Purchasing\Domain\Repository\PurchaseOrderRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Uid\Uuid;

final class DoctrinePurchaseOrderRepository implements PurchaseOrderRepository
{
    public function __construct(private readonly EntityManagerInterface $em)
    {
    }

    public function get(Uuid $companyId, Uuid $id): PurchaseOrder
    {
        return $this->em->getRepository(PurchaseOrder::class)->findOneBy(['companyId' => $companyId, 'id' => $id]) ?? throw new PurchaseOrderNotFound();
    }

    public function add(PurchaseOrder $order): void
    {
        $this->em->persist($order);
    }

    public function remove(PurchaseOrder $order): void
    {
        $this->em->remove($order);
    }
}
