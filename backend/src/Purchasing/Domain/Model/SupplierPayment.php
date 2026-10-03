<?php

namespace App\Purchasing\Domain\Model;

use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Model\DocumentAuditColumns;
use App\Shared\Domain\Model\ReceiptColumns;
use App\Shared\Domain\Money\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A recibo de pago / egreso (§4.11): money paid to a supplier, allocated to its open payables. Emitted when saved.
 */
#[ORM\Entity]
#[ORM\Table(name: 'supplier_payment')]
#[ORM\Index(name: 'supplier_payment_list', columns: ['company_id', 'status', 'receipt_date'])]
#[ORM\UniqueConstraint(name: 'supplier_payment_number', columns: ['company_id', 'prefix', 'sequence'])]
class SupplierPayment implements CompanyOwned
{
    use DocumentAuditColumns;
    use ReceiptColumns;

    /** @var Collection<int, SupplierPaymentAllocation> */
    #[ORM\OneToMany(targetEntity: SupplierPaymentAllocation::class, mappedBy: 'payment', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $allocations;

    /**
     * A receipt is numbered when it is made: it has no draft (§4.11). Allocations are added by the "supplier-payment" item.
     */
    public function __construct(
        Uuid $companyId,
        string $prefix,
        int $sequence,
        Uuid $terceroId,
        string $terceroName,
        \DateTimeImmutable $receiptDate,
        Uuid $paymentMethodId,
        string $methodName,
        Uuid $accountId,
        Money $amount,
        ?string $notes,
        Uuid $createdBy,
        \DateTimeImmutable $createdAt,
    ) {
        $this->initReceipt($companyId, $prefix, $sequence, $terceroId, $terceroName, $receiptDate, $paymentMethodId, $methodName, $accountId, $amount, $notes);
        $this->allocations = new ArrayCollection();
        $this->recordCreation($createdBy, $createdAt);
        $this->recordEmission($createdBy, $createdAt);
    }

    /** @return list<SupplierPaymentAllocation> */
    public function allocations(): array
    {
        return $this->allocations->getValues();
    }
}
