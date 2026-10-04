<?php

namespace App\Sales\Domain\Model;

use App\Shared\Domain\Model\Allocation;
use App\Shared\Domain\Money\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity]
#[ORM\Table(name: 'cash_receipt_allocation')]
#[ORM\Index(name: 'cash_receipt_allocation_invoice', columns: ['company_id', 'invoice_id'])]
class CashReceiptAllocation extends Allocation
{
    public function __construct(
        #[ORM\ManyToOne(targetEntity: CashReceipt::class, inversedBy: 'allocations')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private CashReceipt $receipt,
        Uuid $companyId,
        Uuid $receivableId,
        Uuid $invoiceId,
        string $invoiceNumber,
        Money $amount,
    ) {
        parent::__construct($companyId, $receivableId, $invoiceId, $invoiceNumber, $amount);
    }

    public function receipt(): CashReceipt
    {
        return $this->receipt;
    }
}
