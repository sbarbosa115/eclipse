<?php

namespace App\Ledger\Domain\Model;

use App\Ledger\Domain\Error\EntryEmpty;
use App\Ledger\Domain\Error\EntryUnbalanced;
use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Money\Money;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One balanced entry of the libro diario, made by a document (§5 invariant 2: no manual entries in stage 1). Never
 * edited: a void adds a second entry that reverses it (reversesId).
 */
#[ORM\Entity]
#[ORM\Table(name: 'journal_entry')]
#[ORM\Index(name: 'journal_entry_date', columns: ['company_id', 'entry_date'])]
#[ORM\Index(name: 'journal_entry_source', columns: ['company_id', 'source_type', 'source_id'])]
#[ORM\UniqueConstraint(name: 'journal_entry_number', columns: ['company_id', 'number'])]
class JournalEntry implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    /** @var Collection<int, JournalLine> */
    #[ORM\OneToMany(targetEntity: JournalLine::class, mappedBy: 'entry', cascade: ['persist'], orphanRemoval: false)]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $lines;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        /** Consecutive per company (Company numbering, series journal_entry). */
        #[ORM\Column]
        private int $number,
        #[ORM\Column(type: Types::DATE_IMMUTABLE)]
        private \DateTimeImmutable $entryDate,
        /** sales_invoice, cash_receipt, purchase_invoice, supplier_payment… */
        #[ORM\Column(length: 30)]
        private string $sourceType,
        #[ORM\Column(type: 'uuid')]
        private Uuid $sourceId,
        #[ORM\Column(length: 40)]
        private string $sourceNumber,
        #[ORM\Column(length: 255)]
        private string $description,
        #[ORM\Column(type: 'uuid', nullable: true)]
        private ?Uuid $reversesId,
        #[ORM\Column(type: 'uuid')]
        private Uuid $createdBy,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v7();
        $this->lines = new ArrayCollection();
    }

    public function debit(Uuid $accountId, string $accountCode, ?Uuid $terceroId, Money $amount, ?string $description = null): void
    {
        $this->addLine($accountId, $accountCode, $terceroId, $amount, Money::zero(), $description);
    }

    public function credit(Uuid $accountId, string $accountCode, ?Uuid $terceroId, Money $amount, ?string $description = null): void
    {
        $this->addLine($accountId, $accountCode, $terceroId, Money::zero(), $amount, $description);
    }

    /**
     * Checks the entry before it is stored: at least one movement, and as many débitos as créditos to the cent
     * (§5 invariant 1).
     *
     * @throws EntryEmpty
     * @throws EntryUnbalanced
     */
    public function close(): void
    {
        if ($this->lines->isEmpty()) {
            throw new EntryEmpty();
        }
        if (!$this->isBalanced()) {
            throw new EntryUnbalanced($this->totalDebit(), $this->totalCredit());
        }
    }

    private function addLine(Uuid $accountId, string $accountCode, ?Uuid $terceroId, Money $debit, Money $credit, ?string $description): void
    {
        if ($debit->isNegative() || $credit->isNegative()) {
            throw new \InvalidArgumentException('A movement is a positive débito or crédito.');
        }
        $this->lines->add(new JournalLine($this, $this->companyId, $this->lines->count() + 1, $accountId, $accountCode, $terceroId, $debit, $credit, $description));
    }

    public function totalDebit(): Money
    {
        return Money::sum(...$this->lines->map(static fn (JournalLine $l) => $l->debit())->getValues());
    }

    public function totalCredit(): Money
    {
        return Money::sum(...$this->lines->map(static fn (JournalLine $l) => $l->credit())->getValues());
    }

    public function isBalanced(): bool
    {
        return $this->totalDebit()->equals($this->totalCredit());
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function number(): int
    {
        return $this->number;
    }

    public function entryDate(): \DateTimeImmutable
    {
        return $this->entryDate;
    }

    public function sourceType(): string
    {
        return $this->sourceType;
    }

    public function sourceId(): Uuid
    {
        return $this->sourceId;
    }

    public function sourceNumber(): string
    {
        return $this->sourceNumber;
    }

    public function description(): string
    {
        return $this->description;
    }

    public function reversesId(): ?Uuid
    {
        return $this->reversesId;
    }

    public function createdBy(): Uuid
    {
        return $this->createdBy;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<JournalLine> */
    public function lines(): array
    {
        return $this->lines->getValues();
    }
}
