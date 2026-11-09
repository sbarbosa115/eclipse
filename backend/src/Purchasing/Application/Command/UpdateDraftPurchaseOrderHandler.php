<?php

namespace App\Purchasing\Application\Command;

use App\Purchasing\Domain\Error\DocumentNotDraft;
use App\Purchasing\Domain\Model\PurchaseOrderStatus;
use App\Purchasing\Domain\Repository\PurchaseOrderRepository;
use App\Shared\Application\Command\CommandHandler;

final class UpdateDraftPurchaseOrderHandler implements CommandHandler
{
    public function __construct(
        private readonly PurchaseOrderRepository $orders,
        private readonly PurchaseOrderWriter $writer,
    ) {
    }

    /**
     * @throws DocumentNotDraft
     * @throws \App\Purchasing\Domain\Error\InvalidPurchaseOrder
     */
    public function __invoke(UpdateDraftPurchaseOrder $command): void
    {
        $order = $this->orders->get($command->companyId, $command->orderId);
        if (PurchaseOrderStatus::Draft !== $order->status()) {
            throw new DocumentNotDraft();
        }
        $this->writer->write($order, $command->contents);
    }
}
