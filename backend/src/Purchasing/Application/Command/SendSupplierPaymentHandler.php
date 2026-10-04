<?php

namespace App\Purchasing\Application\Command;

use App\Party\Application\Query\TerceroDirectory;
use App\Purchasing\Domain\Error\DocumentNotEmitted;
use App\Purchasing\Domain\Error\SupplierHasNoEmail;
use App\Purchasing\Domain\Event\SupplierPaymentEmailRequested;
use App\Purchasing\Domain\Repository\SupplierPaymentRepository;
use App\Shared\Application\Command\CommandHandler;
use App\Shared\Application\Event\EventBus;
use App\Shared\Domain\Model\ReceiptStatus;

/** "Enviar por correo" from the list (§4.15): the payment's PDF to the supplier's e-mail, again. */
final class SendSupplierPaymentHandler implements CommandHandler
{
    public function __construct(
        private readonly SupplierPaymentRepository $payments,
        private readonly TerceroDirectory $terceros,
        private readonly EventBus $events,
    ) {
    }

    public function __invoke(SendSupplierPayment $command): void
    {
        $payment = $this->payments->get($command->companyId, $command->paymentId);
        if (ReceiptStatus::Voided === $payment->status()) {
            throw new DocumentNotEmitted();
        }
        if (null === $this->terceros->get($command->companyId, $payment->terceroId())->email) {
            throw new SupplierHasNoEmail();
        }
        $this->events->publish(new SupplierPaymentEmailRequested($command->companyId->toRfc4122(), $payment->id()->toRfc4122()));
    }
}
