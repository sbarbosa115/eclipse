<?php

namespace App\Purchasing\Application\Command;

use App\Purchasing\Domain\Model\PurchaseOrderLine;
use App\Purchasing\Domain\Repository\PurchaseOrderRepository;
use App\Shared\Application\Command\CommandBus;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Domain\Calendar;
use App\Shared\Domain\Model\TaxSnapshot;
use Symfony\Component\Uid\Uuid;

/**
 * Converts an orden de compra into a draft purchase invoice, once (observations PRD §4.8): the invoice command is the
 * one that checks the catalogs, so a supplier, product, account or tax deactivated since is refused there (422, by
 * field path) and nothing changes. The invoice is dated today, has no supplier number and no formas de pago yet and
 * remembers the order; the order becomes converted and keeps the invoice's id.
 */
final class ConvertPurchaseOrderHandler implements CommandHandler
{
    public function __construct(
        private readonly PurchaseOrderRepository $orders,
        private readonly CommandBus $commands,
        private readonly Calendar $calendar,
    ) {
    }

    /**
     * @return Uuid the draft invoice's id
     *
     * @throws \App\Purchasing\Domain\Error\PurchaseOrderAlreadyConverted
     * @throws \App\Purchasing\Domain\Error\PurchaseOrderNotOpen
     * @throws \App\Shared\Domain\Error\InvalidValues the invoice's own violations
     */
    public function __invoke(ConvertPurchaseOrder $command): Uuid
    {
        $order = $this->orders->get($command->companyId, $command->orderId);
        $order->assertConvertible();

        $invoice = $this->commands->dispatch(new CreatePurchaseInvoice($command->companyId, $command->userId, new PurchaseInvoiceContents(
            $order->terceroId(),
            null,
            $this->calendar->today(),
            null,
            $order->notes(),
            array_map(static fn (PurchaseOrderLine $l) => new PurchaseLineContents(
                $l->productId(), $l->accountId(), $l->description(), $l->quantity()->toString(), $l->unitPrice()->toString(), $l->discount()->toString(),
                $l->chargeTax()->taxId(), null,
                // Every retención: the snapshots of a copy are never "no tax".
                array_values(array_filter(array_map(static fn (TaxSnapshot $t) => $t->taxId(), $l->withholdingTaxes()))),
            ), $order->lines()),
            [],
        ), $order->id()));
        \assert($invoice instanceof Uuid);
        $order->convertedTo($invoice);

        return $invoice;
    }
}
