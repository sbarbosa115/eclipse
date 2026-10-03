<?php

namespace App\Sales\Domain\Model;

use App\Sales\Domain\Error\AllocationExceedsBalance;
use App\Sales\Domain\Error\DocumentHasAllocations;
use App\Sales\Domain\Error\DocumentNotDraft;
use App\Sales\Domain\Error\DocumentNotEmitted;
use App\Sales\Domain\Error\InvalidInvoice;
use App\Sales\Domain\Error\PaymentsDoNotMatchTotal;
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
 * A factura de venta (§4.8). A draft has no number; emission takes the resolution's next number and the internal
 * consecutive, freezes it, creates a receivable per crédito payment line and posts its entry. Voiding posts the
 * reversing entry and keeps the number (§4.12).
 */
#[ORM\Entity]
#[ORM\Table(name: 'sales_invoice')]
#[ORM\Index(name: 'sales_invoice_list', columns: ['company_id', 'status', 'issue_date'])]
#[ORM\Index(name: 'sales_invoice_tercero', columns: ['company_id', 'tercero_id'])]
#[ORM\UniqueConstraint(name: 'sales_invoice_number', columns: ['company_id', 'resolution_id', 'authorised_number'])]
class SalesInvoice implements CompanyOwned
{
    use DocumentAuditColumns;
    use DocumentTotalsColumns;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 16, enumType: InvoiceStatus::class)]
    private InvoiceStatus $status = InvoiceStatus::Draft;

    #[ORM\Column(type: 'uuid', nullable: true)]
    #[References('invoicing_resolution')]
    private ?Uuid $resolutionId = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $prefix = null;

    /** The resolution's consecutive (authorised by the DIAN, printed). */
    #[ORM\Column(nullable: true)]
    private ?int $authorisedNumber = null;

    /** The company's internal consecutive across all invoices. */
    #[ORM\Column(nullable: true)]
    private ?int $internalNumber = null;

    /** As printed: prefix-authorised number. */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $number = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $contactId = null;

    /** Vendedor: a tercero with role empleado. */
    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $sellerId = null;

    /** The quotation it was converted from. */
    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $quotationId = null;

    #[ORM\Column(length: 2000, nullable: true)]
    private ?string $notes = null;

    /** What receipts have allocated to it so far. */
    #[ORM\Column(type: 'money')]
    private Money $paidAmount;

    #[ORM\Column(type: 'uuid', nullable: true)]
    #[References('journal_entry')]
    private ?Uuid $journalEntryId = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $reversalEntryId = null;

    /** @var Collection<int, SalesInvoiceLine> */
    #[ORM\OneToMany(targetEntity: SalesInvoiceLine::class, mappedBy: 'document', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    /** @var Collection<int, SalesInvoicePayment> */
    #[ORM\OneToMany(targetEntity: SalesInvoicePayment::class, mappedBy: 'invoice', cascade: ['persist', 'remove'], orphanRemoval: true)]
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

    public function resolutionId(): ?Uuid
    {
        return $this->resolutionId;
    }

    public function prefix(): ?string
    {
        return $this->prefix;
    }

    public function authorisedNumber(): ?int
    {
        return $this->authorisedNumber;
    }

    public function internalNumber(): ?int
    {
        return $this->internalNumber;
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

    public function contactId(): ?Uuid
    {
        return $this->contactId;
    }

    public function sellerId(): ?Uuid
    {
        return $this->sellerId;
    }

    public function quotationId(): ?Uuid
    {
        return $this->quotationId;
    }

    public function issueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
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

    /** @return list<SalesInvoiceLine> */
    public function lines(): array
    {
        return $this->lines->getValues();
    }

    /** @return list<SalesInvoicePayment> */
    public function payments(): array
    {
        return $this->payments->getValues();
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Draft

    /** The header of a draft: client, contact, vendedor, fecha de elaboración and observaciones. */
    public function revise(Uuid $terceroId, string $terceroName, ?Uuid $contactId, ?Uuid $sellerId, \DateTimeImmutable $issueDate, ?string $notes): void
    {
        $this->assertDraft();
        $this->terceroId = $terceroId;
        $this->terceroName = $terceroName;
        $this->contactId = $contactId;
        $this->sellerId = $sellerId;
        $this->issueDate = $issueDate->setTime(0, 0);
        $notes = null === $notes ? '' : trim($notes);
        $this->notes = '' === $notes ? null : $notes;
    }

    /** A draft converted from a cotización keeps it as its origin (§4.7). */
    public function originatesFrom(Uuid $quotationId): void
    {
        $this->assertDraft();
        $this->quotationId = $quotationId;
    }

    /**
     * Replaces every line (in this order) and recomputes the totals.
     *
     * @param list<InvoiceLineDraft> $lines
     */
    public function replaceLines(array $lines): void
    {
        $this->assertDraft();
        $this->lines->clear();
        foreach ($lines as $i => $line) {
            $this->lines->add(new SalesInvoiceLine($this, $this->companyId, $i + 1, $line->productId, trim($line->description), $line->quantity, $line->unitPrice, $line->discount, $line->chargeTax, $line->withholdingTax));
        }
        $this->recomputeTotals($this->lines);
    }

    /**
     * Replaces the formas de pago. A draft may keep rows that do not add up to Total neto yet (§4.6); each row must be
     * well formed.
     *
     * @param list<InvoicePaymentDraft> $payments
     */
    public function replacePayments(array $payments): void
    {
        $this->assertDraft();
        $this->assertPaymentRows(array_map(static fn (InvoicePaymentDraft $p) => [$p->kind, $p->amount, $p->dueDate, $p->accountId], $payments));
        $this->payments->clear();
        foreach ($payments as $i => $p) {
            $this->payments->add(new SalesInvoicePayment($this, $this->companyId, $i + 1, $p->paymentMethodId, $p->methodName, $p->kind, PaymentKind::Cash === $p->kind ? $p->accountId : null, $p->amount, PaymentKind::Credit === $p->kind ? $p->dueDate?->setTime(0, 0) : null));
        }
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Emission

    /**
     * What emission needs from the draft itself (§4.6, §4.8, §9 Q11): a line, a date that is not in the future,
     * well-formed formas de pago that add up to Total neto. The handler asks before it takes a number.
     */
    public function assertEmittable(\DateTimeImmutable $today): void
    {
        $this->assertDraft();
        $violations = [];
        if ($this->lines->isEmpty()) {
            $violations[] = ['field' => 'lines', 'message' => 'Add at least one line.'];
        }
        if ($this->issueDate->format('Y-m-d') > $today->format('Y-m-d')) {
            $violations[] = ['field' => 'issue_date', 'message' => 'The invoice date cannot be in the future.'];
        }
        if ([] !== $violations) {
            throw new InvalidInvoice($violations);
        }
        $this->assertPaymentRows(array_map(static fn (SalesInvoicePayment $p) => [$p->kind(), $p->amount(), $p->dueDate(), $p->accountId()], $this->payments()));

        $paid = Money::sum(...array_map(static fn (SalesInvoicePayment $p) => $p->amount(), $this->payments()));
        if ($this->payments->isEmpty() || !$paid->equals($this->netTotal())) {
            throw new PaymentsDoNotMatchTotal($paid, $this->netTotal());
        }
    }

    /**
     * Emits the draft with the numbers the resolution gave it: it is frozen from now on, and every crédito line opens
     * a receivable due on its date (§9 Q9). An invoice with nothing on crédito is paid at once.
     *
     * @return list<Receivable> to be stored by the caller
     */
    public function emit(Uuid $resolutionId, string $prefix, int $authorisedNumber, int $internalNumber, \DateTimeImmutable $today, Uuid $by, \DateTimeImmutable $at): array
    {
        $this->assertEmittable($today);

        $this->resolutionId = $resolutionId;
        $this->prefix = $prefix;
        $this->authorisedNumber = $authorisedNumber;
        $this->internalNumber = $internalNumber;
        $this->number = $number = '' === $prefix ? (string) $authorisedNumber : $prefix.'-'.$authorisedNumber;
        $this->recordEmission($by, $at);

        $receivables = [];
        foreach ($this->payments() as $payment) {
            if (PaymentKind::Credit === $payment->kind()) {
                $receivables[] = new Receivable($this->companyId, $this->id, $number, $this->terceroId, $this->issueDate, $payment->dueDate() ?? $this->issueDate, $payment->amount());
            }
        }
        $this->status = $this->creditTotal()->isZero() ? InvoiceStatus::Paid : InvoiceStatus::Emitted;

        return $receivables;
    }

    /** The journal entry emission posted (§3: an emitted document owns exactly one). */
    public function recordPosting(Uuid $journalEntryId): void
    {
        $this->journalEntryId = $journalEntryId;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Collection (recibos de caja, item 11)

    /** A receipt applied this much to the invoice's receivables: partially paid, or paid once all is collected. */
    public function applyPayment(Money $amount): void
    {
        $this->assertCollectable();
        if (!$amount->isPositive()) {
            throw new \InvalidArgumentException('An applied amount is positive.');
        }
        $paid = $this->paidAmount->plus($amount);
        if ($paid->isGreaterThan($this->creditTotal())) {
            throw new AllocationExceedsBalance();
        }
        $this->paidAmount = $paid;
        $this->status = $paid->equals($this->creditTotal()) ? InvoiceStatus::Paid : InvoiceStatus::PartiallyPaid;
    }

    /** A receipt applied to it was voided: the amount is owed again. */
    public function revertPayment(Money $amount): void
    {
        $this->assertCollectable();
        $paid = $this->paidAmount->minus($amount);
        if (!$amount->isPositive() || $paid->isNegative()) {
            throw new \LogicException('An invoice never gets back more than was applied to it.');
        }
        $this->paidAmount = $paid;
        $this->status = $paid->isZero() ? InvoiceStatus::Emitted : InvoiceStatus::PartiallyPaid;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Void

    /**
     * Voids the emitted invoice (§4.12): only while no receipt is applied to it, with a reason; the number is kept.
     * The caller posts the reversing entry and voids the receivables.
     */
    public function void(string $reason, bool $hasAllocations, Uuid $by, \DateTimeImmutable $at): void
    {
        if (InvoiceStatus::Draft === $this->status || InvoiceStatus::Voided === $this->status) {
            throw new DocumentNotEmitted();
        }
        if ($hasAllocations || !$this->paidAmount->isZero()) {
            throw new DocumentHasAllocations();
        }
        $reason = trim($reason);
        if ('' === $reason) {
            throw InvalidInvoice::field('reason', 'Write the reason for the void.');
        }
        $this->status = InvoiceStatus::Voided;
        $this->recordVoid($by, $at, mb_substr($reason, 0, 500));
    }

    /** The reversing entry the void posted. */
    public function recordReversal(Uuid $journalEntryId): void
    {
        $this->reversalEntryId = $journalEntryId;
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Reading

    /** What the formas de pago left on crédito: the receivables' original amounts. */
    public function creditTotal(): Money
    {
        $credit = array_filter($this->payments(), static fn (SalesInvoicePayment $p) => PaymentKind::Credit === $p->kind());

        return Money::sum(...array_map(static fn (SalesInvoicePayment $p) => $p->amount(), $credit));
    }

    /** What the client still owes on it: nothing on a draft or a voided invoice. */
    public function balance(): Money
    {
        return match ($this->status) {
            InvoiceStatus::Draft, InvoiceStatus::Voided => Money::zero(),
            default => $this->creditTotal()->minus($this->paidAmount),
        };
    }

    private function assertDraft(): void
    {
        if (!$this->status->isEditable()) {
            throw new DocumentNotDraft();
        }
    }

    private function assertCollectable(): void
    {
        if (InvoiceStatus::Draft === $this->status || InvoiceStatus::Voided === $this->status) {
            throw new DocumentNotEmitted();
        }
    }

    /**
     * @param list<array{PaymentKind, Money, ?\DateTimeImmutable, ?Uuid}> $rows kind, amount, due date, account
     */
    private function assertPaymentRows(array $rows): void
    {
        $violations = [];
        foreach ($rows as $i => [$kind, $amount, $due, $account]) {
            if (!$amount->isPositive()) {
                $violations[] = ['field' => "payments.$i.amount", 'message' => 'Write an amount greater than zero.'];
            }
            if (PaymentKind::Credit === $kind) {
                if (null === $due) {
                    $violations[] = ['field' => "payments.$i.due_date", 'message' => 'A credit payment needs its due date.'];
                } elseif ($due->format('Y-m-d') < $this->issueDate->format('Y-m-d')) {
                    $violations[] = ['field' => "payments.$i.due_date", 'message' => 'The due date cannot be before the invoice date.'];
                }
            } elseif (null === $account) {
                $violations[] = ['field' => "payments.$i.payment_method_id", 'message' => 'This payment method has no account to receive the money.'];
            }
        }
        if ([] !== $violations) {
            throw new InvalidInvoice($violations);
        }
    }
}
