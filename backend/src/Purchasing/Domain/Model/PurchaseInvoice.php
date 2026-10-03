<?php

namespace App\Purchasing\Domain\Model;

use App\Purchasing\Domain\Error\AllocationExceedsBalance;
use App\Purchasing\Domain\Error\DocumentAlreadyVoided;
use App\Purchasing\Domain\Error\DocumentHasAllocations;
use App\Purchasing\Domain\Error\DocumentHasNoLines;
use App\Purchasing\Domain\Error\DocumentNotDraft;
use App\Purchasing\Domain\Error\DocumentNotEmitted;
use App\Purchasing\Domain\Error\IssueDateInFuture;
use App\Purchasing\Domain\Error\PaymentsDoNotMatchTotal;
use App\Purchasing\Domain\Error\SupplierInvoiceNumberRequired;
use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Model\DocumentAuditColumns;
use App\Shared\Domain\Model\DocumentTotalsColumns;
use App\Shared\Domain\Model\InvoiceStatus;
use App\Shared\Domain\Model\PaymentKind;
use App\Shared\Domain\Model\References;
use App\Shared\Domain\Money\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A factura de compra / gasto (§4.10): the supplier's invoice as the company records it. The supplier's number is
 * unique per supplier; the internal number (series FC) is taken at emission, which creates a payable per crédito
 * line and posts the entry (Appendix A.3). Only a draft changes; an emitted invoice is voided (reversing entry) while
 * no payment is allocated to it, and its payables are paid down by supplier payments (applyPayment).
 */
#[ORM\Entity]
#[ORM\Table(name: 'purchase_invoice')]
#[ORM\Index(name: 'purchase_invoice_list', columns: ['company_id', 'status', 'issue_date'])]
#[ORM\UniqueConstraint(name: 'purchase_invoice_supplier_number', columns: ['company_id', 'tercero_id', 'supplier_invoice_number'])]
class PurchaseInvoice implements CompanyOwned
{
    use DocumentAuditColumns;
    use DocumentTotalsColumns;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 16, enumType: InvoiceStatus::class)]
    private InvoiceStatus $status = InvoiceStatus::Draft;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $prefix = null;

    #[ORM\Column(nullable: true)]
    private ?int $sequence = null;

    /** The internal number as printed (FC-12). */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $number = null;

    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $dueDate = null;

    #[ORM\Column(length: 2000, nullable: true)]
    private ?string $notes = null;

    /** What payments have allocated to it so far. */
    #[ORM\Column(type: 'money')]
    private Money $paidAmount;

    #[ORM\Column(type: 'uuid', nullable: true)]
    #[References('journal_entry')]
    private ?Uuid $journalEntryId = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $reversalEntryId = null;

    /** @var Collection<int, PurchaseInvoiceLine> */
    #[ORM\OneToMany(targetEntity: PurchaseInvoiceLine::class, mappedBy: 'document', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    /** @var Collection<int, PurchaseInvoicePayment> */
    #[ORM\OneToMany(targetEntity: PurchaseInvoicePayment::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $payments;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(type: 'uuid')]
        #[References('tercero')]
        private Uuid $terceroId,
        #[ORM\Column(length: 200)]
        private string $terceroName,
        /** The supplier's own number: a draft may wait for it, emission needs it. */
        #[ORM\Column(length: 40, nullable: true)]
        private ?string $supplierInvoiceNumber,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $issueDate,
        Uuid $createdBy,
        \DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v7();
        $this->lines = new ArrayCollection();
        $this->payments = new ArrayCollection();
        $this->paidAmount = Money::zero();
        $this->zeroTotals();
        $this->recordCreation($createdBy, $createdAt);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function status(): InvoiceStatus
    {
        return $this->status;
    }

    public function prefix(): ?string
    {
        return $this->prefix;
    }

    public function sequence(): ?int
    {
        return $this->sequence;
    }

    public function number(): ?string
    {
        return $this->number;
    }

    public function terceroId(): Uuid
    {
        return $this->terceroId;
    }

    public function terceroName(): string
    {
        return $this->terceroName;
    }

    public function supplierInvoiceNumber(): ?string
    {
        return $this->supplierInvoiceNumber;
    }

    public function issueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function dueDate(): ?\DateTimeImmutable
    {
        return $this->dueDate;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function paidAmount(): Money
    {
        return $this->paidAmount;
    }

    public function journalEntryId(): ?Uuid
    {
        return $this->journalEntryId;
    }

    public function reversalEntryId(): ?Uuid
    {
        return $this->reversalEntryId;
    }

    /** @return list<PurchaseInvoiceLine> */
    public function lines(): array
    {
        return $this->lines->getValues();
    }

    /** @return list<PurchaseInvoicePayment> */
    public function payments(): array
    {
        return $this->payments->getValues();
    }

    /**
     * Rewrites the draft: header, lines and formas de pago (§4.6), and recomputes its totals.
     *
     * @param list<PurchaseLineDraft>    $lines
     * @param list<PurchasePaymentDraft> $payments
     *
     * @throws DocumentNotDraft
     */
    public function revise(Uuid $terceroId, string $terceroName, ?string $supplierInvoiceNumber, \DateTimeImmutable $issueDate, ?\DateTimeImmutable $dueDate, ?string $notes, array $lines, array $payments): void
    {
        $this->assertDraft();
        $this->terceroId = $terceroId;
        $this->terceroName = $terceroName;
        $this->supplierInvoiceNumber = $supplierInvoiceNumber;
        $this->issueDate = $issueDate;
        $this->dueDate = $dueDate;
        $this->notes = $notes;

        $this->lines->clear();
        foreach (array_values($lines) as $position => $l) {
            $this->lines->add(new PurchaseInvoiceLine($this, $this->companyId, $position, $l->productId, $l->accountId, $l->description, $l->quantity, $l->unitPrice, $l->discount, $l->chargeTax, $l->withholdingTax));
        }
        $this->payments->clear();
        foreach (array_values($payments) as $position => $p) {
            $this->payments->add(new PurchaseInvoicePayment($this, $this->companyId, $position, $p->paymentMethodId, $p->methodName, $p->kind, $p->accountId, $p->amount, $p->dueDate));
        }
        $this->recomputeTotals($this->lines);
    }

    /**
     * Whether the draft may be emitted on $today (Colombian calendar): the supplier's number, a line, a date not in the
     * future and formas de pago that add up to the total neto. The lock date and the supplier's state are the
     * handler's to check.
     */
    public function assertEmittable(\DateTimeImmutable $today): void
    {
        $this->assertDraft();
        if (null === $this->supplierInvoiceNumber || '' === trim($this->supplierInvoiceNumber)) {
            throw new SupplierInvoiceNumberRequired();
        }
        if ($this->lines->isEmpty()) {
            throw new DocumentHasNoLines();
        }
        if ($this->issueDate->format('Y-m-d') > $today->format('Y-m-d')) {
            throw new IssueDateInFuture();
        }
        $paid = Money::sum(...array_map(static fn (PurchaseInvoicePayment $p) => $p->amount(), $this->payments()));
        if ($this->payments->isEmpty() || !$paid->equals($this->netTotal())) {
            throw new PaymentsDoNotMatchTotal($paid, $this->netTotal());
        }
    }

    /**
     * Freezes the draft under its internal number and opens one payable per crédito payment line, due on that line's
     * date (or the invoice's).
     *
     * @return list<Payable>
     */
    public function emit(string $prefix, int $sequence, Uuid $by, \DateTimeImmutable $at, \DateTimeImmutable $today): array
    {
        $this->assertEmittable($today);
        $this->prefix = $prefix;
        $this->sequence = $sequence;
        $this->number = '' === $prefix ? (string) $sequence : $prefix.'-'.$sequence;
        $this->status = InvoiceStatus::Emitted;
        $this->recordEmission($by, $at);

        $payables = [];
        foreach ($this->payments() as $payment) {
            if (PaymentKind::Credit === $payment->kind()) {
                $payables[] = new Payable($this->companyId, $this->id, $this->number, $this->terceroId, $this->issueDate, $payment->dueDate() ?? $this->dueDate ?? $this->issueDate, $payment->amount());
            }
        }

        return $payables;
    }

    /** The journal entry the emission posted. */
    public function recordEntry(Uuid $entryId): void
    {
        $this->journalEntryId = $entryId;
    }

    /**
     * Whether it may be voided: emitted, not voided yet, and nothing paid (§4.12).
     *
     * @throws DocumentNotEmitted|DocumentAlreadyVoided|DocumentHasAllocations
     */
    public function assertVoidable(): void
    {
        match ($this->status) {
            InvoiceStatus::Draft => throw new DocumentNotEmitted(),
            InvoiceStatus::Voided => throw new DocumentAlreadyVoided(),
            InvoiceStatus::PartiallyPaid, InvoiceStatus::Paid => throw new DocumentHasAllocations(),
            InvoiceStatus::Emitted => $this->paidAmount->isZero() ? null : throw new DocumentHasAllocations(),
        };
    }

    /** Voids it with the reversing entry already posted; the number, the original entry and the lines stay. */
    public function void(string $reason, Uuid $by, \DateTimeImmutable $at, Uuid $reversalEntryId): void
    {
        $this->assertVoidable();
        $this->status = InvoiceStatus::Voided;
        $this->reversalEntryId = $reversalEntryId;
        $this->recordVoid($by, $at, $reason);
    }

    /**
     * A supplier payment allocated to one of its payables (§4.11): partially paid, or paid once the payments reach
     * the total neto.
     */
    public function applyPayment(Money $amount): void
    {
        $this->assertPayable();
        $owed = $this->netTotal()->minus($this->paidAmount);
        if ($amount->isGreaterThan($owed)) {
            throw new AllocationExceedsBalance($amount, $owed);
        }
        $this->paidAmount = $this->paidAmount->plus($amount);
        $this->followPayments();
    }

    /** A supplier payment voided: what it paid is owed again. */
    public function unapplyPayment(Money $amount): void
    {
        $this->assertPayable();
        if ($amount->isGreaterThan($this->paidAmount)) {
            throw new AllocationExceedsBalance($amount, $this->paidAmount);
        }
        $this->paidAmount = $this->paidAmount->minus($amount);
        $this->followPayments();
    }

    /**
     * A new draft like this one (§4.15 duplicate): the same supplier, lines, taxes and formas de pago, dated $today,
     * with each due date the same number of days after it. The supplier's number is the new invoice's own, so it is
     * left to type.
     */
    public function duplicate(Uuid $by, \DateTimeImmutable $at, \DateTimeImmutable $today): self
    {
        $issue = new \DateTimeImmutable($today->format('Y-m-d'));
        $shift = fn (?\DateTimeImmutable $due) => null === $due ? null : $issue->modify(\sprintf('%+d days', (int) $this->issueDate->diff($due)->format('%r%a')));

        $copy = new self($this->companyId, $this->terceroId, $this->terceroName, null, $issue, $by, $at);
        $copy->revise(
            $this->terceroId,
            $this->terceroName,
            null,
            $issue,
            $shift($this->dueDate),
            $this->notes,
            array_map(static fn (PurchaseInvoiceLine $l) => new PurchaseLineDraft($l->productId(), $l->accountId(), $l->description(), $l->quantity(), $l->unitPrice(), $l->discount(), $l->chargeTax(), $l->withholdingTax()), $this->lines()),
            array_map(static fn (PurchaseInvoicePayment $p) => new PurchasePaymentDraft($p->paymentMethodId(), $p->methodName(), $p->kind(), $p->accountId(), $p->amount(), $shift($p->dueDate())), $this->payments()),
        );

        return $copy;
    }

    public function isDraft(): bool
    {
        return InvoiceStatus::Draft === $this->status;
    }

    private function assertDraft(): void
    {
        if (!$this->status->isEditable()) {
            throw new DocumentNotDraft();
        }
    }

    private function assertPayable(): void
    {
        match ($this->status) {
            InvoiceStatus::Draft => throw new DocumentNotEmitted(),
            InvoiceStatus::Voided => throw new DocumentAlreadyVoided(),
            default => null,
        };
    }

    private function followPayments(): void
    {
        $this->status = match (true) {
            $this->paidAmount->isZero() => InvoiceStatus::Emitted,
            $this->paidAmount->equals($this->netTotal()) => InvoiceStatus::Paid,
            default => InvoiceStatus::PartiallyPaid,
        };
    }
}
