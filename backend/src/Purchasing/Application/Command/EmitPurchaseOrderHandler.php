<?php

namespace App\Purchasing\Application\Command;

use App\Company\Application\Numbering\Numbering;
use App\Party\Application\Query\TerceroDirectory;
use App\Purchasing\Domain\Error\SupplierHasNoEmail;
use App\Purchasing\Domain\Error\SupplierInactive;
use App\Purchasing\Domain\Event\PurchaseOrderEmailRequested;
use App\Purchasing\Domain\Repository\PurchaseOrderRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Calendar;

/**
 * Emission of an orden de compra (observations PRD §4.8), in one transaction: the draft's own checks, an active
 * supplier, the next number of series OC. It posts **nothing** (no JournalPoster here, on purpose).
 */
final class EmitPurchaseOrderHandler implements CommandHandler
{
    public function __construct(
        private readonly PurchaseOrderRepository $orders,
        private readonly TerceroDirectory $terceros,
        private readonly Numbering $numbering,
        private readonly EventBus $events,
        private readonly Calendar $calendar,
    ) {
    }

    public function __invoke(EmitPurchaseOrder $command): void
    {
        $order = $this->orders->get($command->companyId, $command->orderId);
        $today = $this->calendar->today();
        $order->assertEmittable($today);

        $supplier = $this->terceros->get($command->companyId, $order->terceroId());
        if (!$supplier->active) {
            throw new SupplierInactive();
        }
        if ($command->send && null === $supplier->email) {
            throw new SupplierHasNoEmail();
        }

        $number = $this->numbering->purchaseOrder($command->companyId);
        $order->emit($number->prefix, $number->sequence, $today, $command->userId, $this->calendar->now());

        if ($command->send) {
            $this->events->publish(new PurchaseOrderEmailRequested($command->companyId->toRfc4122(), $order->id()->toRfc4122()));
        }
    }
}
