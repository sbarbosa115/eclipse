<?php

namespace App\Sales\Domain\Model;

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
}
