<?php

namespace App\Sales\Domain\Model;

use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Model\PaymentLine;
use App\Shared\Domain\Money\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'sales_invoice_payment')]
class SalesInvoicePayment extends PaymentLine
{
    public function __construct(
        #[ORM\ManyToOne(targetEntity: SalesInvoice::class, inversedBy: 'payments')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private SalesInvoice $invoice,
        Uuid $companyId,
        int $position,
        Uuid $paymentMethodId,
        string $methodName,
        PaymentKind $kind,
        ?Uuid $accountId,
        Money $amount,
        ?\DateTimeImmutable $dueDate,
    ) {
        parent::__construct($companyId, $position, $paymentMethodId, $methodName, $kind, $accountId, $amount, $dueDate);
    }

    public function invoice(): SalesInvoice
    {
        return $this->invoice;
    }
}
