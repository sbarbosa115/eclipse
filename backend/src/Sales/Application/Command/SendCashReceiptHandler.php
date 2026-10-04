<?php

namespace App\Sales\Application\Command;

use App\Party\Application\Query\TerceroDirectory;
use App\Sales\Domain\Error\DocumentNotEmitted;
use App\Sales\Domain\Error\TerceroHasNoEmail;
use App\Sales\Domain\Event\CashReceiptEmailRequested;
use App\Sales\Domain\Repository\CashReceiptRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Model\ReceiptStatus;

/** "Enviar por correo" from the list (§4.15): the receipt's PDF to the client's billing e-mail, again. */
final class SendCashReceiptHandler implements CommandHandler
{
    public function __construct(
        private readonly CashReceiptRepository $receipts,
        private readonly TerceroDirectory $terceros,
        private readonly EventBus $events,
    ) {
    }

    public function __invoke(SendCashReceipt $command): void
    {
        $receipt = $this->receipts->get($command->companyId, $command->receiptId);
        if (ReceiptStatus::Voided === $receipt->status()) {
            throw new DocumentNotEmitted();
        }
        if (null === $this->terceros->get($command->companyId, $receipt->terceroId())->email) {
            throw new TerceroHasNoEmail();
        }
        $this->events->publish(new CashReceiptEmailRequested($command->companyId->toRfc4122(), $receipt->id()->toRfc4122()));
    }
}
