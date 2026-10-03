<?php

namespace App\Sales\Domain\Model;

use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Model\DocumentAuditColumns;
use App\Shared\Domain\Model\ReceiptColumns;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use App\Shared\Domain\Money\Money;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A recibo de caja (§4.9): money received from a client, allocated to its open receivables. Emitted when saved.
 */
#[ORM\Entity]
#[ORM\Table(name: 'cash_receipt')]
#[ORM\Index(name: 'cash_receipt_list', columns: ['company_id', 'status', 'receipt_date'])]
#[ORM\UniqueConstraint(name: 'cash_receipt_number', columns: ['company_id', 'prefix', 'sequence'])]
class CashReceipt implements CompanyOwned
{
    use DocumentAuditColumns;
    use ReceiptColumns;

    /** @var Collection<int, CashReceiptAllocation> */
    #[ORM\OneToMany(targetEntity: CashReceiptAllocation::class, mappedBy: 'receipt', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $allocations;

    /**
     * A receipt is numbered when it is made: it has no draft (§4.9). Allocations are added by the "cash-receipt" item.
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

    /** @return list<CashReceiptAllocation> */
    public function allocations(): array
    {
        return $this->allocations->getValues();
    }
}
