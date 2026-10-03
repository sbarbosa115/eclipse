<?php

namespace App\Ledger\Domain\Model;

use App\Shared\Domain\Model\CompanyOwned;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * An account of the company's chart (§4.1, §4.13 Plan de cuentas). The PUC's are "standard": they cannot be deleted,
 * only deactivated. The accountant adds sub-accounts and auxiliares under them.
 */
#[ORM\Entity]
#[ORM\Table(name: 'ledger_account')]
#[ORM\UniqueConstraint(name: 'ledger_account_code', columns: ['company_id', 'code'])]
#[ORM\Index(name: 'ledger_account_parent', columns: ['company_id', 'parent_code'])]
class Account implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 12, enumType: AccountLevel::class)]
    private AccountLevel $level;

    #[ORM\Column]
    private bool $active = true;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(length: 16)]
        private string $code,
        #[ORM\Column(length: 200)]
        private string $name,
        #[ORM\Column(length: 8, enumType: AccountNature::class)]
        private AccountNature $nature,
        #[ORM\Column(length: 16, nullable: true)]
        private ?string $parentCode,
        /** From the PUC seed: never deleted. */
        #[ORM\Column]
        private bool $standard,
        /** May be chosen directly on a purchase line (§9 Q14: classes 5, 6, 7 by default). */
        #[ORM\Column]
        private bool $usableOnPurchases = false,
    ) {
        $this->id = Uuid::v7();
        $this->level = AccountLevel::ofCode($code);
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function nature(): AccountNature
    {
        return $this->nature;
    }

    public function level(): AccountLevel
    {
        return $this->level;
    }

    public function parentCode(): ?string
    {
        return $this->parentCode;
    }

    public function isStandard(): bool
    {
        return $this->standard;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function isUsableOnPurchases(): bool
    {
        return $this->usableOnPurchases;
    }

    public function isPostable(): bool
    {
        return $this->active && $this->level->isPostable();
    }
}
