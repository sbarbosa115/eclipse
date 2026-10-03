<?php

namespace App\Purchasing\Domain\Model;

use App\Shared\Domain\Model\Allocation;
use App\Shared\Domain\Money\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'supplier_payment_allocation')]
#[ORM\Index(name: 'supplier_payment_allocation_invoice', columns: ['company_id', 'invoice_id'])]
class SupplierPaymentAllocation extends Allocation
{
    public function __construct(
        #[ORM\ManyToOne(targetEntity: SupplierPayment::class, inversedBy: 'allocations')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private SupplierPayment $payment,
        Uuid $companyId,
        Uuid $payableId,
        Uuid $invoiceId,
        string $invoiceNumber,
        Money $amount,
    ) {
        parent::__construct($companyId, $payableId, $invoiceId, $invoiceNumber, $amount);
    }

    public function payment(): SupplierPayment
    {
        return $this->payment;
    }
}
