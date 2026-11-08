<?php

namespace App\Purchasing\Application\Command;

use App\Party\Application\Query\TerceroDirectory;
use App\Purchasing\Domain\Error\PurchaseOrderNotOpen;
use App\Purchasing\Domain\Error\SupplierHasNoEmail;
use App\Purchasing\Domain\Event\PurchaseOrderEmailRequested;
use App\Purchasing\Domain\Repository\PurchaseOrderRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;

final class SendPurchaseOrderHandler implements CommandHandler
{
    public function __construct(
        private readonly PurchaseOrderRepository $orders,
        private readonly TerceroDirectory $terceros,
        private readonly EventBus $events,
    ) {
    }

    public function __invoke(SendPurchaseOrder $command): void
    {
        $order = $this->orders->get($command->companyId, $command->orderId);
        if (!$order->isOpen()) {
            throw new PurchaseOrderNotOpen();
        }
        if (null === $this->terceros->get($command->companyId, $order->terceroId())->email) {
            throw new SupplierHasNoEmail();
        }
        $this->events->publish(new PurchaseOrderEmailRequested($command->companyId->toRfc4122(), $order->id()->toRfc4122()));
    }
}
