<?php

namespace App\Sales\Domain\Model;

use App\Sales\Domain\Error\DocumentNotDraft;
use App\Sales\Domain\Error\InvalidQuotation;
use App\Sales\Domain\Error\QuotationAlreadyConverted;
use App\Sales\Domain\Error\QuotationNotOpen;
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
    /** §9 Q17: how long an offer is valid unless the person says otherwise. */
    public const VALIDITY_DAYS = 30;

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

    // ---------------------------------------------------------------------------------------------------------------
    // Draft

    /**
     * The header of a draft: client, contact, responsable, fechas, encabezado, condiciones comerciales and
     * observaciones. The texts are plain text, kept as typed (trimmed) and escaped wherever they are shown.
     */
    public function revise(Uuid $terceroId, string $terceroName, ?Uuid $contactId, ?Uuid $responsibleId, \DateTimeImmutable $issueDate, ?\DateTimeImmutable $expiryDate, ?string $header, ?string $terms, ?string $notes): void
    {
        $this->assertDraft();
        $issueDate = $issueDate->setTime(0, 0);
        $expiryDate = null === $expiryDate ? $issueDate->modify(\sprintf('+%d days', self::VALIDITY_DAYS)) : $expiryDate->setTime(0, 0);
        if ($expiryDate < $issueDate) {
            throw InvalidQuotation::field('expiry_date', 'The offer cannot expire before the quotation date.');
        }
        $this->terceroId = $terceroId;
        $this->terceroName = $terceroName;
        $this->contactId = $contactId;
        $this->responsibleId = $responsibleId;
        $this->issueDate = $issueDate;
        $this->expiryDate = $expiryDate;
        $this->header = self::text($header);
        $this->terms = self::text($terms);
        $this->notes = self::text($notes);
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
            $this->lines->add(new QuotationLine($this, $this->companyId, $i + 1, $line->productId, trim($line->description), $line->quantity, $line->unitPrice, $line->discount, $line->chargeTax, $line->withholdingTax));
        }
        $this->recomputeTotals($this->lines);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // Emission

    /** What emission needs of the draft (§4.6, §9 Q11): a line and a date that is not in the future. */
    public function assertEmittable(\DateTimeImmutable $today): void
    {
        $this->assertDraft();
        $violations = [];
        if ($this->lines->isEmpty()) {
            $violations[] = ['field' => 'lines', 'message' => 'Add at least one line.'];
        }
        if ($this->issueDate->format('Y-m-d') > $today->format('Y-m-d')) {
            $violations[] = ['field' => 'issue_date', 'message' => 'The quotation date cannot be in the future.'];
        }
        if ([] !== $violations) {
            throw new InvalidQuotation($violations);
        }
    }

    /** Emits the draft with the number series C gave it: frozen from now on. It has no accounting effect (§4.7). */
    public function emit(string $prefix, int $sequence, \DateTimeImmutable $today, Uuid $by, \DateTimeImmutable $at): void
    {
        $this->assertEmittable($today);
        $this->prefix = $prefix;
        $this->sequence = $sequence;
        $this->number = '' === $prefix ? (string) $sequence : $prefix.'-'.$sequence;
        $this->status = QuotationStatus::Emitted;
        $this->recordEmission($by, $at);
    }

    // ---------------------------------------------------------------------------------------------------------------
    // After emission

    /**
     * What the quotation is on a given day: an emitted one past its fecha de vencimiento reads as `expired` (§4.7).
     * Nothing is stored: the day is the clock's, so there is no job to run and no stale status to correct.
     */
    public function statusOn(\DateTimeImmutable $today): QuotationStatus
    {
        if (QuotationStatus::Emitted === $this->status && $this->expiryDate->format('Y-m-d') < $today->format('Y-m-d')) {
            return QuotationStatus::Expired;
        }

        return $this->status;
    }

    /** The client said yes. */
    public function accept(): void
    {
        $this->assertOpen();
        $this->status = QuotationStatus::Accepted;
    }

    /** The client said no. */
    public function reject(): void
    {
        $this->assertOpen();
        $this->status = QuotationStatus::Rejected;
    }

    /** Voids an emitted quotation, with a reason; it keeps its number. */
    public function void(string $reason, Uuid $by, \DateTimeImmutable $at): void
    {
        if (QuotationStatus::Emitted !== $this->status) {
            throw new QuotationNotOpen();
        }
        $reason = trim($reason);
        if ('' === $reason) {
            throw InvalidQuotation::field('reason', 'Write the reason for the void.');
        }
        $this->status = QuotationStatus::Voided;
        $this->recordVoid($by, $at, mb_substr($reason, 0, 500));
    }

    /**
     * Whether it may become an invoice: once (§9 Q17), and only while it stands — emitted and valid, or accepted by
     * hand and not converted yet.
     *
     * @throws QuotationAlreadyConverted
     * @throws QuotationNotOpen
     */
    public function assertConvertible(): void
    {
        if (null !== $this->convertedInvoiceId) {
            throw new QuotationAlreadyConverted();
        }
        if (QuotationStatus::Accepted !== $this->status) {
            $this->assertOpen();
        }
    }

    /** The draft invoice that was made from it: the quotation is accepted and keeps the invoice's id. */
    public function convertedTo(Uuid $invoiceId): void
    {
        $this->assertConvertible();
        $this->convertedInvoiceId = $invoiceId;
        $this->status = QuotationStatus::Accepted;
    }

    private function assertDraft(): void
    {
        if (QuotationStatus::Draft !== $this->status) {
            throw new DocumentNotDraft();
        }
    }

    /**
     * Emitted and not yet decided. A lapsed offer (`expired`) is still open: a client may accept it late, and it is then
     * accepted, rejected or converted like any other (decided 2026-10-04).
     */
    private function assertOpen(): void
    {
        if (QuotationStatus::Emitted !== $this->status) {
            throw new QuotationNotOpen();
        }
    }

    private static function text(?string $text): ?string
    {
        $text = null === $text ? '' : trim($text);

        return '' === $text ? null : $text;
    }
}
