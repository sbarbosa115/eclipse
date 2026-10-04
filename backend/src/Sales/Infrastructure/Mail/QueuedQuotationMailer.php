<?php

namespace App\Sales\Infrastructure\Mail;

use App\Sales\Application\Port\QuotationEmail;
use App\Sales\Application\Port\QuotationMailer;
use App\Shared\Infrastructure\Mail\QueuedMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;

final class QueuedQuotationMailer implements QuotationMailer
{
    public function __construct(
        private readonly QueuedMailer $mailer,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $from,
    ) {
    }

    public function send(QuotationEmail $email): void
    {
        $message = (new TemplatedEmail())
            ->from(new Address($this->from, $email->companyName))
            ->to(new Address($email->to, $email->clientName))
            ->subject(\sprintf('Cotización %s de %s', $email->number, $email->companyName))
            ->htmlTemplate('email/quotation/quotation.html.twig')
            ->textTemplate('email/quotation/quotation.txt.twig')
            ->context(['client' => $email->clientName, 'company' => $email->companyName, 'number' => $email->number, 'net_total' => $email->netTotal, 'expiry_date' => $email->expiryDate])
            ->attach($email->pdf, $email->fileName, 'application/pdf');

        $this->mailer->send($message, 'quotation');
    }
}
