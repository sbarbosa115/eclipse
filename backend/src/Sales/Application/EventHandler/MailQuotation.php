<?php

namespace App\Sales\Application\EventHandler;

use App\Party\Application\Query\TerceroDirectory;
use App\Sales\Application\Document\QuotationPdf;
use App\Sales\Application\Port\QuotationEmail;
use App\Sales\Application\Port\QuotationMailer;
use App\Sales\Domain\Event\QuotationEmailRequested;
use App\Shared\Application\Event\EventHandler;
use Symfony\Component\Uid\Uuid;

/** "Emitir y enviar" / "Enviar por correo": the PDF goes to the client's e-mail, through the queue. */
final class MailQuotation implements EventHandler
{
    public function __construct(
        private readonly QuotationPdf $pdf,
        private readonly TerceroDirectory $terceros,
        private readonly QuotationMailer $mailer,
    ) {
    }

    public function __invoke(QuotationEmailRequested $event): void
    {
        $companyId = Uuid::fromString($event->companyId);
        $document = $this->pdf->render($companyId, Uuid::fromString($event->quotationId));
        $client = $this->terceros->get($companyId, Uuid::fromString($document->terceroId));
        if (null === $client->email) {
            return;
        }
        $this->mailer->send(new QuotationEmail($client->email, $client->displayName, $document->companyName, $document->number, $document->netTotal, $document->expiryDate, $document->bytes, $document->fileName));
    }
}
