<?php

namespace App\Purchasing\Domain\Model;

use App\Purchasing\Domain\Error\AllocationExceedsBalance;
use App\Purchasing\Domain\Error\InvalidPayment;
use App\Purchasing\Domain\Error\PaymentAllocationsDoNotMatchAmount;
use App\Purchasing\Domain\Error\PaymentAlreadyVoided;
use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Model\DocumentAuditColumns;
use App\Shared\Domain\Model\ReceiptColumns;
use App\Shared\Domain\Model\ReceiptStatus;
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
     * A receipt is numbered when it is made: it has no draft (§4.11). Use issue(), which checks and allocates.
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

    /**
     * Pays a supplier (§4.11), all checked before anything is kept: Valor pagado > 0, a date not after today (§9 Q11:
     * backdating is allowed; the lock date is the ledger's to check), at least one allocation, each positive, to an open
     * payable of this supplier, once, and no more than its balance (allocation_exceeds_balance); and the allocations add
     * up to the amount exactly (allocations_do_not_match_amount: no anticipos and no over-payment, §9 Q16).
     *
     * The payables' balances are not moved here: the handler applies each allocation through PayableAllocations, which
     * also moves the invoice's status.
     *
     * @param list<PayableAllocation> $allocations
     *
     * @throws InvalidPayment
     * @throws AllocationExceedsBalance
     * @throws PaymentAllocationsDoNotMatchAmount
     */
    public static function issue(
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
        array $allocations,
        \DateTimeImmutable $today,
        Uuid $by,
        \DateTimeImmutable $at,
    ): self {
        self::check($terceroId, $receiptDate, $amount, $allocations, $today);

        $payment = new self($companyId, $prefix, $sequence, $terceroId, $terceroName, $receiptDate, $paymentMethodId, $methodName, $accountId, $amount, $notes, $by, $at);
        foreach ($allocations as $allocation) {
            $payable = $allocation->payable;
            $payment->allocations->add(new SupplierPaymentAllocation($payment, $companyId, $payable->id(), $payable->invoiceId(), $payable->invoiceNumber(), $allocation->amount));
        }

        return $payment;
    }

    /**
     * @param list<PayableAllocation> $allocations
     */
    private static function check(Uuid $terceroId, \DateTimeImmutable $receiptDate, Money $amount, array $allocations, \DateTimeImmutable $today): void
    {
        $violations = [];
        if (!$amount->isPositive()) {
            $violations[] = ['field' => 'amount', 'message' => 'Write an amount greater than zero.'];
        }
        if ($receiptDate->format('Y-m-d') > $today->format('Y-m-d')) {
            $violations[] = ['field' => 'receipt_date', 'message' => 'The payment date cannot be in the future.'];
        }
        if ([] === $allocations) {
            $violations[] = ['field' => 'allocations', 'message' => 'Allocate the amount paid to at least one invoice.'];
        }
        $seen = [];
        foreach ($allocations as $i => $allocation) {
            $payable = $allocation->payable;
            $key = $payable->id()->toRfc4122();
            if (!$payable->terceroId()->equals($terceroId) || $payable->isVoided() || isset($seen[$key])) {
                $violations[] = ['field' => "allocations.$i.payable_id", 'message' => 'Choose an open invoice of this supplier, once.'];
            } elseif (!$allocation->amount->isPositive()) {
                $violations[] = ['field' => "allocations.$i.amount", 'message' => 'Write an amount greater than zero.'];
            }
            $seen[$key] = true;
        }
        if ([] !== $violations) {
            throw new InvalidPayment($violations);
        }

        $allocated = Money::zero();
        foreach ($allocations as $allocation) {
            if ($allocation->amount->isGreaterThan($allocation->payable->balance())) {
                throw new AllocationExceedsBalance($allocation->amount, $allocation->payable->balance());
            }
            $allocated = $allocated->plus($allocation->amount);
        }
        if (!$allocated->equals($amount)) {
            throw new PaymentAllocationsDoNotMatchAmount($allocated, $amount);
        }
    }

    /**
     * Voids the payment (§4.12): at any time, with a reason; the number is kept. The caller gives each allocation back
     * through PayableAllocations and posts the reversing entry.
     */
    public function void(string $reason, Uuid $by, \DateTimeImmutable $at): void
    {
        if (ReceiptStatus::Voided === $this->status()) {
            throw new PaymentAlreadyVoided();
        }
        $reason = trim($reason);
        if ('' === $reason) {
            throw InvalidPayment::field('reason', 'Write the reason for the void.');
        }
        $this->status = ReceiptStatus::Voided;
        $this->recordVoid($by, $at, $reason);
    }

    public function recordPosting(Uuid $entryId): void
    {
        $this->journalEntryId = $entryId;
    }

    public function recordReversal(Uuid $entryId): void
    {
        $this->reversalEntryId = $entryId;
    }

    /** @return list<SupplierPaymentAllocation> */
    public function allocations(): array
    {
        return $this->allocations->getValues();
    }
}
