<?php

namespace App\Purchasing\Application\Command;

use App\Purchasing\Domain\Model\PurchaseLineDraft;
use App\Purchasing\Domain\Model\PurchaseOrder;
use App\Purchasing\Domain\Model\PurchaseOrderLine;
use App\Purchasing\Domain\Repository\PurchaseOrderRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Calendar;
use Symfony\Component\Uid\Uuid;

/** A new draft dated today; the expected delivery keeps its distance from the order date. */
final class DuplicatePurchaseOrderHandler implements CommandHandler
{
    public function __construct(
        private readonly PurchaseOrderRepository $orders,
        private readonly Calendar $calendar,
    ) {
    }

    /** @return Uuid the new draft's id */
    public function __invoke(DuplicatePurchaseOrder $command): Uuid
    {
        $source = $this->orders->get($command->companyId, $command->orderId);
        $today = $this->calendar->today();
        $expected = null;
        if (null !== $source->expectedDate()) {
            $days = max(0, (int) $source->issueDate()->diff($source->expectedDate())->format('%r%a'));
            $expected = $today->modify(\sprintf('+%d days', $days));
        }

        $copy = new PurchaseOrder($command->companyId, $source->terceroId(), $source->terceroName(), $today, $command->userId, $this->calendar->now());
        $copy->revise($source->terceroId(), $source->terceroName(), $today, $expected, $source->notes());
        $copy->replaceLines(array_map(static fn (PurchaseOrderLine $l) => new PurchaseLineDraft($l->productId(), $l->accountId(), $l->description(), $l->quantity(), $l->unitPrice(), $l->discount(), $l->chargeTax(), $l->withholdingTaxes(), $l->productCode()), $source->lines()));
        $this->orders->add($copy);

        return $copy->id();
    }
}
