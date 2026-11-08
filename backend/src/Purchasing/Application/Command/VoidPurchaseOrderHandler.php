<?php

namespace App\Purchasing\Application\Command;

use App\Purchasing\Domain\Repository\PurchaseOrderRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Calendar;

final class VoidPurchaseOrderHandler implements CommandHandler
{
    public function __construct(
        private readonly PurchaseOrderRepository $orders,
        private readonly Calendar $calendar,
    ) {
    }

    /**
     * @throws \App\Purchasing\Domain\Error\PurchaseOrderNotOpen
     * @throws \App\Purchasing\Domain\Error\InvalidPurchaseOrder
     */
    public function __invoke(VoidPurchaseOrder $command): void
    {
        $this->orders->get($command->companyId, $command->orderId)->void($command->reason, $command->userId, $this->calendar->now());
    }
}
