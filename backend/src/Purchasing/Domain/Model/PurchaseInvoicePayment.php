<?php

namespace App\Purchasing\Domain\Model;

use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Model\PaymentLine;
use App\Shared\Domain\Money\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'purchase_invoice_payment')]
class PurchaseInvoicePayment extends PaymentLine
{
    public function __construct(
        #[ORM\ManyToOne(targetEntity: PurchaseInvoice::class, inversedBy: 'payments')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private PurchaseInvoice $invoice,
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

    public function invoice(): PurchaseInvoice
    {
        return $this->invoice;
    }
}
