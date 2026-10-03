<?php

namespace App\Ledger\Domain\Model;

use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Money\Money;
use App\Shared\Domain\Model\References;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * One movement of an entry: a débito or a crédito (one of them zero) on a postable account, with the tercero when the
 * account tracks them (1305, 2205, 2365…). The account code is copied so reports group without a join.
 */
#[ORM\Entity]
#[ORM\Table(name: 'journal_line')]
#[ORM\Index(name: 'journal_line_account', columns: ['company_id', 'account_code'])]
#[ORM\Index(name: 'journal_line_tercero', columns: ['company_id', 'tercero_id', 'account_code'])]
class JournalLine implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    public function __construct(
        #[ORM\ManyToOne(targetEntity: JournalEntry::class, inversedBy: 'lines')]
        #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
        private JournalEntry $entry,
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column]
        private int $position,
        #[ORM\Column(type: 'uuid')]
        #[References('ledger_account')]
        private Uuid $accountId,
        #[ORM\Column(length: 16)]
        private string $accountCode,
        #[ORM\Column(type: 'uuid', nullable: true)]
        #[References('tercero')]
        private ?Uuid $terceroId,
        #[ORM\Column(type: 'money')]
        private Money $debit,
        #[ORM\Column(type: 'money')]
        private Money $credit,
        #[ORM\Column(length: 255, nullable: true)]
        private ?string $description,
    ) {
        $this->id = Uuid::v7();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function entry(): JournalEntry
    {
        return $this->entry;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function position(): int
    {
        return $this->position;
    }

    public function accountId(): Uuid
    {
        return $this->accountId;
    }

    public function accountCode(): string
    {
        return $this->accountCode;
    }

    public function terceroId(): ?Uuid
    {
        return $this->terceroId;
    }

    public function debit(): Money
    {
        return $this->debit;
    }

    public function credit(): Money
    {
        return $this->credit;
    }

    public function description(): ?string
    {
        return $this->description;
    }
}
