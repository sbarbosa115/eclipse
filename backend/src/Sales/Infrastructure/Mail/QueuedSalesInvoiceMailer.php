<?php

namespace App\Sales\Infrastructure\Mail;

use App\Sales\Application\Port\SalesInvoiceEmail;
use App\Sales\Application\Port\SalesInvoiceMailer;
use App\Shared\Infrastructure\Mail\QueuedMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;

final class QueuedSalesInvoiceMailer implements SalesInvoiceMailer
{
    public function __construct(
        private readonly QueuedMailer $mailer,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $from,
    ) {
    }

    public function send(SalesInvoiceEmail $email): void
    {
        $message = (new TemplatedEmail())
            ->from(new Address($this->from, $email->companyName))
            ->to(new Address($email->to, $email->clientName))
            ->subject(\sprintf('Factura de venta %s de %s', $email->number, $email->companyName))
            ->htmlTemplate('email/sales_invoice/invoice.html.twig')
            ->textTemplate('email/sales_invoice/invoice.txt.twig')
            ->context(['client' => $email->clientName, 'company' => $email->companyName, 'number' => $email->number, 'net_total' => $email->netTotal])
            ->attach($email->pdf, $email->fileName, 'application/pdf');

        $this->mailer->send($message, 'sales_invoice');
    }
}
