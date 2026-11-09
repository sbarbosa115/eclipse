<?php

namespace App\Purchasing\Infrastructure\Mail;

use App\Purchasing\Application\Port\PurchaseOrderEmail;
use App\Purchasing\Application\Port\PurchaseOrderMailer;
use App\Shared\Infrastructure\Mail\QueuedMailer;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mime\Address;

final class QueuedPurchaseOrderMailer implements PurchaseOrderMailer
{
    public function __construct(
        private readonly QueuedMailer $mailer,
        #[Autowire(env: 'MAILER_FROM')]
        private readonly string $from,
    ) {
    }

    public function send(PurchaseOrderEmail $email): void
    {
        $message = (new TemplatedEmail())
            ->from(new Address($this->from, $email->companyName))
            ->to(new Address($email->to, $email->supplierName))
            ->subject(\sprintf('Orden de compra %s de %s', $email->number, $email->companyName))
            ->htmlTemplate('email/purchase_order/order.html.twig')
            ->textTemplate('email/purchase_order/order.txt.twig')
            ->context(['supplier' => $email->supplierName, 'company' => $email->companyName, 'number' => $email->number, 'net_total' => $email->netTotal, 'expected_date' => $email->expectedDate])
            ->attach($email->pdf, $email->fileName, 'application/pdf');

        $this->mailer->send($message, 'purchase_order');
    }
}
