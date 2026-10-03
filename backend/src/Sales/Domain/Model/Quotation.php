<?php

namespace App\Sales\Domain\Model;

use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Model\DocumentAuditColumns;
use App\Shared\Domain\Model\DocumentTotalsColumns;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use App\Shared\Domain\Model\References;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A cotización (§4.7): an invoice's shape with no accounting effect. Numbered from series C at emission; converts
 * once into a draft sales invoice.
 */
#[ORM\Entity]
#[ORM\Table(name: 'quotation')]
#[ORM\Index(name: 'quotation_list', columns: ['company_id', 'status', 'issue_date'])]
#[ORM\Index(name: 'quotation_tercero', columns: ['company_id', 'tercero_id'])]
class Quotation implements CompanyOwned
{
    use DocumentAuditColumns;
    use DocumentTotalsColumns;

    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 16, enumType: QuotationStatus::class)]
    private QuotationStatus $status = QuotationStatus::Draft;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $prefix = null;

    #[ORM\Column(nullable: true)]
    private ?int $sequence = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $number = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $contactId = null;

    /** Responsable de la cotización: a tercero with role empleado. */
    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $responsibleId = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $header = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $terms = null;

    #[ORM\Column(length: 2000, nullable: true)]
    private ?string $notes = null;

    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $convertedInvoiceId = null;

    /** @var Collection<int, QuotationLine> */
    #[ORM\OneToMany(targetEntity: QuotationLine::class, mappedBy: 'document', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['position' => 'ASC'])]
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
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $expiryDate,
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

    public function status(): QuotationStatus
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

    public function contactId(): ?Uuid
    {
        return $this->contactId;
    }

    public function responsibleId(): ?Uuid
    {
        return $this->responsibleId;
    }

    public function issueDate(): \DateTimeImmutable
    {
        return $this->issueDate;
    }

    public function expiryDate(): \DateTimeImmutable
    {
        return $this->expiryDate;
    }

    public function header(): ?string
    {
        return $this->header;
    }

    public function terms(): ?string
    {
        return $this->terms;
    }

    public function notes(): ?string
    {
        return $this->notes;
    }

    public function convertedInvoiceId(): ?Uuid
    {
        return $this->convertedInvoiceId;
    }

    /** @return list<QuotationLine> */
    public function lines(): array
    {
        return $this->lines->getValues();
    }
}
