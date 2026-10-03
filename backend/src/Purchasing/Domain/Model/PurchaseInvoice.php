<?php

namespace App\Purchasing\Domain\Model;

use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Model\DocumentAuditColumns;
use App\Shared\Domain\Model\DocumentTotalsColumns;
use App\Shared\Domain\Model\InvoiceStatus;
use App\Shared\Domain\Money\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use App\Shared\Domain\Model\References;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A factura de compra / gasto (§4.10): the supplier's invoice as the company records it. The supplier's number is
 * unique per supplier; the internal number (series FC) is taken at emission, which creates a payable per crédito
 * line and posts the entry (Appendix A.3).
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
        #[ORM\Column(length: 40)]
        private string $supplierInvoiceNumber,
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

    public function supplierInvoiceNumber(): string
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
}
