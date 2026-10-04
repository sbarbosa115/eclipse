<?php

namespace App\Purchasing\Infrastructure\Mail;

use App\Purchasing\Application\Port\SupplierPaymentEmail;
use App\Purchasing\Application\Port\SupplierPaymentMailer;
use App\Shared\Infrastructure\Mail\QueuedMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;

final class QueuedSupplierPaymentMailer implements SupplierPaymentMailer
{
    public function __construct(
        private readonly QueuedMailer $mailer,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $from,
    ) {
    }

    public function send(SupplierPaymentEmail $email): void
    {
        $message = (new TemplatedEmail())
            ->from(new Address($this->from, $email->companyName))
            ->to(new Address($email->to, $email->supplierName))
            ->subject(\sprintf('Recibo de pago %s de %s', $email->number, $email->companyName))
            ->htmlTemplate('email/supplier_payment/payment.html.twig')
            ->textTemplate('email/supplier_payment/payment.txt.twig')
            ->context(['supplier' => $email->supplierName, 'company' => $email->companyName, 'number' => $email->number, 'amount' => $email->amount])
            ->attach($email->pdf, $email->fileName, 'application/pdf');

        $this->mailer->send($message, 'supplier_payment');
    }
}
