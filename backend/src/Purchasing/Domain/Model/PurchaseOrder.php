<?php

namespace App\Purchasing\Domain\Model;

use App\Purchasing\Domain\Error\DocumentNotDraft;
use App\Purchasing\Domain\Error\InvalidPurchaseOrder;
use App\Purchasing\Domain\Error\PurchaseOrderAlreadyConverted;
use App\Purchasing\Domain\Error\PurchaseOrderNotOpen;
use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Model\DocumentAuditColumns;
use App\Shared\Domain\Model\DocumentTotalsColumns;
use App\Shared\Domain\Model\References;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * An orden de compra (observations PRD §4.8): what the company asks a supplier for, with a factura de compra's lines
 * and no formas de pago. Numbered from series OC at emission; posts nothing; converts once into a draft purchase
 * invoice.
 */
#[ORM\Entity]
#[ORM\Table(name: 'purchase_order')]
#[ORM\Index(name: 'purchase_order_list', columns: ['company_id', 'status', 'issue_date'])]
#[ORM\Index(name: 'purchase_order_tercero', columns: ['company_id', 'tercero_id'])]
class PurchaseOrder implements CompanyOwned
{
    use DocumentAuditColumns;
    use DocumentTotalsColumns;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 16, enumType: PurchaseOrderStatus::class)]
    private PurchaseOrderStatus $status = PurchaseOrderStatus::Draft;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $prefix = null;

    #[ORM\Column(nullable: true)]
    private ?int $sequence = null;

    /** The number as printed (OC-3). */
    #[ORM\Column(length: 40, nullable: true)]
    private ?string $number = null;

    /** Fecha esperada de entrega. */
    #[ORM\Column(type: Types::DATE_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expectedDate = null;

    #[ORM\Column(length: 2000, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $convertedInvoiceId = null;

    /** @var Collection<int, PurchaseOrderLine> */
    #[ORM\OneToMany(targetEntity: PurchaseOrderLine::class, mappedBy: 'document', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => \SortDirection::Ascending])]
    private Collection $lines;

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

    public function status(): PurchaseOrderStatus
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

    public function issueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function expectedDate(): ?\DateTimeImmutable
    {
        return $this->expectedDate;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function convertedInvoiceId(): ?Uuid
    {
        return $this->convertedInvoiceId;
    }

    /** @return list<PurchaseOrderLine> */
    public function lines(): array
    {
        return $this->lines->getValues();
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Draft

    /** The header of a draft: supplier, fecha, fecha esperada de entrega and observaciones (plain text, trimmed). */
    public function revise(Uuid $terceroId, string $terceroName, \DateTimeImmutable $issueDate, ?\DateTimeImmutable $expectedDate, ?string $notes): void
    {
        $this->assertDraft();
        $issueDate = $issueDate->setTime(0, 0);
        $expectedDate = $expectedDate?->setTime(0, 0);
        if (null !== $expectedDate && $expectedDate < $issueDate) {
            throw InvalidPurchaseOrder::field('expected_date', 'The expected delivery cannot be before the order date.');
        }
        $this->terceroId = $terceroId;
        $this->terceroName = $terceroName;
        $this->issueDate = $issueDate;
        $this->expectedDate = $expectedDate;
        $notes = null === $notes ? '' : trim($notes);
        $this->notes = '' === $notes ? null : $notes;
    }

    /**
     * Replaces every line (in this order) and recomputes the totals.
     *
     * @param list<PurchaseLineDraft> $lines
     */
    public function replaceLines(array $lines): void
    {
        $this->assertDraft();
        $this->lines->clear();
        foreach ($lines as $i => $line) {
            $added = new PurchaseOrderLine($this, $this->companyId, $i + 1, $line->productId, $line->accountId, trim($line->description), $line->quantity, $line->unitPrice, $line->discount, $line->chargeTax, $line->withholdings);
            $added->useProductCode($line->productCode);
            $this->lines->add($added);
        }
        $this->recomputeTotals($this->lines);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Emission

    /** What emission needs of the draft: a line and a date that is not in the future. */
    public function assertEmittable(\DateTimeImmutable $today): void
    {
        $this->assertDraft();
        $violations = [];
        if ($this->lines->isEmpty()) {
            $violations[] = ['field' => 'lines', 'message' => 'Add at least one line.'];
        }
        if ($this->issueDate->format('Y-m-d') > $today->format('Y-m-d')) {
            $violations[] = ['field' => 'issue_date', 'message' => 'The order date cannot be in the future.'];
        }
        if ([] !== $violations) {
            throw new InvalidPurchaseOrder($violations);
        }
    }

    /** Emits the draft with the number series OC gave it: frozen from now on. It has no accounting effect. */
    public function emit(string $prefix, int $sequence, \DateTimeImmutable $today, Uuid $by, \DateTimeImmutable $at): void
    {
        $this->assertEmittable($today);
        $this->prefix = $prefix;
        $this->sequence = $sequence;
        $this->number = '' === $prefix ? (string) $sequence : $prefix.'-'.$sequence;
        $this->status = PurchaseOrderStatus::Emitted;
        $this->recordEmission($by, $at);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // After emission

    /** Whether the order is out to the supplier and still waiting: emitted, not converted, not voided. */
    public function isOpen(): bool
    {
        return PurchaseOrderStatus::Emitted === $this->status;
    }

    /** Voids an emitted order, with a reason; it keeps its number and has nothing to reverse. */
    public function void(string $reason, Uuid $by, \DateTimeImmutable $at): void
    {
        $this->assertOpen();
        $reason = trim($reason);
        if ('' === $reason) {
            throw InvalidPurchaseOrder::field('reason', 'Write the reason for the void.');
        }
        $this->status = PurchaseOrderStatus::Voided;
        $this->recordVoid($by, $at, mb_substr($reason, 0, 500));
    }

    /**
     * Whether it may become an invoice: once, and only while it stands (emitted).
     *
     * @throws PurchaseOrderAlreadyConverted
     * @throws PurchaseOrderNotOpen
     */
    public function assertConvertible(): void
    {
        if (null !== $this->convertedInvoiceId) {
            throw new PurchaseOrderAlreadyConverted();
        }
        $this->assertOpen();
    }

    /** The draft invoice that was made from it: the order is converted and keeps the invoice's id. */
    public function convertedTo(Uuid $invoiceId): void
    {
        $this->assertConvertible();
        $this->convertedInvoiceId = $invoiceId;
        $this->status = PurchaseOrderStatus::Converted;
    }

    private function assertDraft(): void
    {
        if (PurchaseOrderStatus::Draft !== $this->status) {
            throw new DocumentNotDraft();
        }
    }

    private function assertOpen(): void
    {
        if (PurchaseOrderStatus::Emitted !== $this->status) {
            throw new PurchaseOrderNotOpen();
        }
    }
}
