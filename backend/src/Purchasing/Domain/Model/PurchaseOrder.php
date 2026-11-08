<?php

namespace App\Purchasing\Domain\Model;

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
 * invoice. Contract only (item 0): its behaviour is the "purchase-orders" item's.
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
}
