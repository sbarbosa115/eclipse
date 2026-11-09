<?php

namespace App\Purchasing\Application\EventHandler;

use App\Party\Application\Query\TerceroDirectory;
use App\Purchasing\Application\Document\PurchaseOrderPdf;
use App\Purchasing\Application\Port\PurchaseOrderEmail;
use App\Purchasing\Application\Port\PurchaseOrderMailer;
use App\Purchasing\Domain\Event\PurchaseOrderEmailRequested;
use App\Shared\Application\Event\EventHandler;
use Symfony\Component\Uid\Uuid;

/** "Emitir y enviar" / "Enviar por correo": the PDF goes to the supplier's e-mail, through the queue. */
final class MailPurchaseOrder implements EventHandler
{
    public function __construct(
        private readonly PurchaseOrderPdf $pdf,
        private readonly TerceroDirectory $terceros,
        private readonly PurchaseOrderMailer $mailer,
    ) {
    }

    public function __invoke(PurchaseOrderEmailRequested $event): void
    {
        $companyId = Uuid::fromString($event->companyId);
        $document = $this->pdf->render($companyId, Uuid::fromString($event->orderId));
        $supplier = $this->terceros->get($companyId, Uuid::fromString($document->terceroId));
        if (null === $supplier->email) {
            return;
        }
        $this->mailer->send(new PurchaseOrderEmail($supplier->email, $supplier->displayName, $document->companyName, $document->number, $document->netTotal, $document->expectedDate, $document->bytes, $document->fileName));
    }
}
