<?php

namespace App\Purchasing\Application\EventHandler;

use App\Party\Application\Query\TerceroDirectory;
use App\Purchasing\Application\Document\SupplierPaymentPdf;
use App\Purchasing\Application\Port\SupplierPaymentEmail;
use App\Purchasing\Application\Port\SupplierPaymentMailer;
use App\Purchasing\Domain\Event\SupplierPaymentEmailRequested;
use App\Shared\Application\Event\EventHandler;
use Symfony\Component\Uid\Uuid;

/** *Guardar y enviar* / "Enviar por correo": the payment's PDF goes to the supplier's e-mail, queued. */
final class MailSupplierPayment implements EventHandler
{
    public function __construct(
        private readonly SupplierPaymentPdf $pdf,
        private readonly TerceroDirectory $terceros,
        private readonly SupplierPaymentMailer $mailer,
    ) {
    }

    public function __invoke(SupplierPaymentEmailRequested $event): void
    {
        $companyId = Uuid::fromString($event->companyId);
        $document = $this->pdf->render($companyId, Uuid::fromString($event->paymentId));
        $supplier = $this->terceros->get($companyId, Uuid::fromString($document->terceroId));
        if (null === $supplier->email) {
            return;
        }
        $this->mailer->send(new SupplierPaymentEmail($supplier->email, $supplier->displayName, $document->companyName, $document->number, $document->amount, $document->bytes, $document->fileName));
    }
}
