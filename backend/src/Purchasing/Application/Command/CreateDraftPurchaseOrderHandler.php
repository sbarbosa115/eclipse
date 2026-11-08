<?php

namespace App\Purchasing\Application\Command;

use App\Purchasing\Domain\Repository\PurchaseOrderRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Calendar;
use Symfony\Component\Uid\Uuid;

final class CreateDraftPurchaseOrderHandler implements CommandHandler
{
    public function __construct(
        private readonly PurchaseOrderRepository $orders,
        private readonly PurchaseOrderWriter $writer,
        private readonly Calendar $calendar,
    ) {
    }

    /**
     * @return Uuid the new draft's id
     *
     * @throws \App\Purchasing\Domain\Error\InvalidPurchaseOrder
     */
    public function __invoke(CreateDraftPurchaseOrder $command): Uuid
    {
        $order = $this->writer->create($command->companyId, $command->userId, $command->contents, $this->calendar->now());
        $this->orders->add($order);

        return $order->id();
    }
}
