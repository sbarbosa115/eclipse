<?php

namespace App\Sales\Infrastructure\Mail;

use App\Sales\Application\Port\CashReceiptEmail;
use App\Sales\Application\Port\CashReceiptMailer;
use App\Shared\Infrastructure\Mail\QueuedMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;

final class QueuedCashReceiptMailer implements CashReceiptMailer
{
    public function __construct(
        private readonly QueuedMailer $mailer,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $from,
    ) {
    }

    public function send(CashReceiptEmail $email): void
    {
        $message = (new TemplatedEmail())
            ->from(new Address($this->from, $email->companyName))
            ->to(new Address($email->to, $email->clientName))
            ->subject(\sprintf('Recibo de caja %s de %s', $email->number, $email->companyName))
            ->htmlTemplate('email/cash_receipt/receipt.html.twig')
            ->textTemplate('email/cash_receipt/receipt.txt.twig')
            ->context(['client' => $email->clientName, 'company' => $email->companyName, 'number' => $email->number, 'amount' => $email->amount])
            ->attach($email->pdf, $email->fileName, 'application/pdf');

        $this->mailer->send($message, 'cash_receipt');
    }
}
