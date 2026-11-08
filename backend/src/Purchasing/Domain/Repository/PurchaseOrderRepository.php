<?php

namespace App\Purchasing\Domain\Repository;

use App\Purchasing\Domain\Model\PurchaseOrder;
use Symfony\Component\Uid\Uuid;

interface PurchaseOrderRepository
{
    /** @throws \App\Purchasing\Domain\Error\PurchaseOrderNotFound another company's id answers the same */
    public function get(Uuid $companyId, Uuid $id): PurchaseOrder;

    public function add(PurchaseOrder $order): void;

    public function remove(PurchaseOrder $order): void;
}
