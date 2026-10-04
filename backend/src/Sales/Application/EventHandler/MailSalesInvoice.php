<?php

namespace App\Sales\Application\EventHandler;

use App\Party\Application\Query\TerceroDirectory;
use App\Sales\Application\Document\SalesInvoicePdf;
use App\Sales\Application\Port\SalesInvoiceEmail;
use App\Sales\Application\Port\SalesInvoiceMailer;
use App\Sales\Domain\Event\SalesInvoiceEmailRequested;
use App\Shared\Application\Event\EventHandler;
use Symfony\Component\Uid\Uuid;

/** "Emitir y enviar" / "Enviar por correo": the PDF goes to the client's billing e-mail, through the queue. */
final class MailSalesInvoice implements EventHandler
{
    public function __construct(
        private readonly SalesInvoicePdf $pdf,
        private readonly TerceroDirectory $terceros,
        private readonly SalesInvoiceMailer $mailer,
    ) {
    }

    public function __invoke(SalesInvoiceEmailRequested $event): void
    {
        $companyId = Uuid::fromString($event->companyId);
        $document = $this->pdf->render($companyId, Uuid::fromString($event->invoiceId));
        $client = $this->terceros->get($companyId, Uuid::fromString($document->terceroId));
        if (null === $client->email) {
            return;
        }
        $this->mailer->send(new SalesInvoiceEmail($client->email, $client->displayName, $document->companyName, $document->number, $document->netTotal, $document->bytes, $document->fileName));
    }
}
