<?php

namespace App\Ledger\Domain\Model;

use App\Shared\Domain\Model\CompanyOwned;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Which DIAN exógena concept an account's movements report under, per format (observations PRD §4.7 d): accounts
 * starting with $accountPrefix (the longest prefix wins) report under $conceptCode in $format (1001, 1007). The
 * accountant edits the list; it is seeded with the obvious defaults.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ledger_exogena_concept')]
#[ORM\UniqueConstraint(name: 'ledger_exogena_concept_prefix', columns: ['company_id', 'format', 'account_prefix'])]
class ExogenaConcept implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(length: 4)]
        private string $format,
        #[ORM\Column(length: 16)]
        private string $accountPrefix,
        #[ORM\Column(length: 8)]
        private string $conceptCode,
    ) {
        $this->id = Uuid::v7();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function format(): string
    {
        return $this->format;
    }

    public function accountPrefix(): string
    {
        return $this->accountPrefix;
    }

    public function conceptCode(): string
    {
        return $this->conceptCode;
    }

    /** The accountant points the prefix at another DIAN concept. */
    public function reportUnder(string $conceptCode): void
    {
        $this->conceptCode = $conceptCode;
    }
}
