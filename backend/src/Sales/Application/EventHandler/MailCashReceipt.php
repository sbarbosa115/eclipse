<?php

namespace App\Sales\Application\EventHandler;

use App\Party\Application\Query\TerceroDirectory;
use App\Sales\Application\Document\CashReceiptPdf;
use App\Sales\Application\Port\CashReceiptEmail;
use App\Sales\Application\Port\CashReceiptMailer;
use App\Sales\Domain\Event\CashReceiptEmailRequested;
use App\Shared\Application\Event\EventHandler;
use Symfony\Component\Uid\Uuid;

/** *Guardar y enviar por mail* / "Enviar por correo": the receipt's PDF goes to the client's billing e-mail, queued. */
final class MailCashReceipt implements EventHandler
{
    public function __construct(
        private readonly CashReceiptPdf $pdf,
        private readonly TerceroDirectory $terceros,
        private readonly CashReceiptMailer $mailer,
    ) {
    }

    public function __invoke(CashReceiptEmailRequested $event): void
    {
        $companyId = Uuid::fromString($event->companyId);
        $document = $this->pdf->render($companyId, Uuid::fromString($event->receiptId));
        $client = $this->terceros->get($companyId, Uuid::fromString($document->terceroId));
        if (null === $client->email) {
            return;
        }
        $this->mailer->send(new CashReceiptEmail($client->email, $client->displayName, $document->companyName, $document->number, $document->amount, $document->bytes, $document->fileName));
    }
}
