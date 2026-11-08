<?php

namespace App\Purchasing\Application\Command;

use App\Purchasing\Domain\Error\DocumentNotDraft;
use App\Purchasing\Domain\Model\PurchaseOrderStatus;
use App\Purchasing\Domain\Repository\PurchaseOrderRepository;
use App\Shared\Application\Command\CommandHandler;

final class DeleteDraftPurchaseOrderHandler implements CommandHandler
{
    public function __construct(private readonly PurchaseOrderRepository $orders)
    {
    }

    public function __invoke(DeleteDraftPurchaseOrder $command): void
    {
        $order = $this->orders->get($command->companyId, $command->orderId);
        if (PurchaseOrderStatus::Draft !== $order->status()) {
            throw new DocumentNotDraft();
        }
        $this->orders->remove($order);
    }
}
