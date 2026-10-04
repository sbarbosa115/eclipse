<?php

namespace App\Sales\Domain\Model;

use App\Sales\Domain\Error\AllocationExceedsBalance;
use App\Sales\Domain\Error\AllocationsDoNotMatchAmount;
use App\Sales\Domain\Error\InvalidReceipt;
use App\Sales\Domain\Error\ReceiptAlreadyVoided;
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
     * A receipt is numbered when it is made: it has no draft (§4.9). Use issue(), which checks and allocates.
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
     * Receives money from a client and allocates it (§4.9), all checked before anything is kept: Valor recibido > 0,
     * a date not after today (§9 Q11: backdating is allowed; the lock date is the ledger's to check), at least one
     * allocation, each positive, to an open receivable of this client, once, and no more than its balance
     * (allocation_exceeds_balance); and the allocations add up to the amount exactly (allocations_do_not_match_amount:
     * no anticipos and no over-payment, §9 Q16).
     *
     * The receivables' balances are not moved here: the handler applies each allocation through InvoiceCollections,
     * which also moves the invoice's status.
     *
     * @param list<ReceivableAllocation> $allocations
     *
     * @throws InvalidReceipt
     * @throws AllocationExceedsBalance
     * @throws AllocationsDoNotMatchAmount
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

        $receipt = new self($companyId, $prefix, $sequence, $terceroId, $terceroName, $receiptDate, $paymentMethodId, $methodName, $accountId, $amount, $notes, $by, $at);
        foreach ($allocations as $allocation) {
            $receivable = $allocation->receivable;
            $receipt->allocations->add(new CashReceiptAllocation($receipt, $companyId, $receivable->id(), $receivable->invoiceId(), $receivable->invoiceNumber(), $allocation->amount));
        }

        return $receipt;
    }

    /**
     * @param list<ReceivableAllocation> $allocations
     */
    private static function check(Uuid $terceroId, \DateTimeImmutable $receiptDate, Money $amount, array $allocations, \DateTimeImmutable $today): void
    {
        $violations = [];
        if (!$amount->isPositive()) {
            $violations[] = ['field' => 'amount', 'message' => 'Write an amount greater than zero.'];
        }
        if ($receiptDate->format('Y-m-d') > $today->format('Y-m-d')) {
            $violations[] = ['field' => 'receipt_date', 'message' => 'The receipt date cannot be in the future.'];
        }
        if ([] === $allocations) {
            $violations[] = ['field' => 'allocations', 'message' => 'Allocate the amount to at least one invoice.'];
        }
        $seen = [];
        foreach ($allocations as $i => $allocation) {
            $receivable = $allocation->receivable;
            $key = $receivable->id()->toRfc4122();
            if (!$receivable->terceroId()->equals($terceroId) || $receivable->isVoided() || isset($seen[$key])) {
                $violations[] = ['field' => "allocations.$i.receivable_id", 'message' => 'Choose an open invoice of this client, once.'];
            } elseif (!$allocation->amount->isPositive()) {
                $violations[] = ['field' => "allocations.$i.amount", 'message' => 'Write an amount greater than zero.'];
            }
            $seen[$key] = true;
        }
        if ([] !== $violations) {
            throw new InvalidReceipt($violations);
        }

        $allocated = Money::zero();
        foreach ($allocations as $allocation) {
            if ($allocation->amount->isGreaterThan($allocation->receivable->balance())) {
                throw new AllocationExceedsBalance();
            }
            $allocated = $allocated->plus($allocation->amount);
        }
        if (!$allocated->equals($amount)) {
            throw new AllocationsDoNotMatchAmount($allocated, $amount);
        }
    }

    /**
     * Voids the receipt (§4.12): at any time, with a reason; the number is kept. The caller gives each allocation back
     * through InvoiceCollections and posts the reversing entry.
     */
    public function void(string $reason, Uuid $by, \DateTimeImmutable $at): void
    {
        if (ReceiptStatus::Voided === $this->status) {
            throw new ReceiptAlreadyVoided();
        }
        $reason = trim($reason);
        if ('' === $reason) {
            throw InvalidReceipt::field('reason', 'Write the reason for the void.');
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

    /** @return list<CashReceiptAllocation> */
    public function allocations(): array
    {
        return $this->allocations->getValues();
    }
}
