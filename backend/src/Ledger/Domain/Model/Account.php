<?php

namespace App\Ledger\Domain\Model;

use App\Ledger\Domain\Error\AccountCodeInvalid;
use App\Ledger\Domain\Error\StandardAccountName;
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

    /**
     * A sub-account or auxiliar the company adds under one of its accounts (§4.1: under a cuenta or deeper, Art. 6:
     * auxiliares of 8+ digits). Its code is the parent's plus two digits; it runs on the parent's side, and accounts of
     * classes 5, 6 and 7 may be chosen on purchase lines unless said otherwise (§9 Q14).
     *
     * @throws AccountCodeInvalid
     */
    public static function under(self $parent, string $code, string $name, ?bool $usableOnPurchases = null): self
    {
        $code = trim($code);
        if (\strlen($parent->code) < 4 || !preg_match('/^\d+$/', $code) || \strlen($code) !== \strlen($parent->code) + 2 || !str_starts_with($code, $parent->code)) {
            throw new AccountCodeInvalid();
        }

        return new self($parent->companyId, $code, trim($name), $parent->nature, $parent->code, false, $usableOnPurchases ?? self::isCostOrExpense($code));
    }

    /** Classes 5 (gastos), 6 (costos de ventas) and 7 (costos de producción): what a purchase line may post to. */
    public static function isCostOrExpense(string $code): bool
    {
        return \in_array($code[0] ?? '', ['5', '6', '7'], true);
    }

    /** @throws StandardAccountName the PUC's names are the Decreto's */
    public function rename(string $name): void
    {
        $name = trim($name);
        if ($name === $this->name) {
            return;
        }
        if ($this->standard) {
            throw new StandardAccountName();
        }
        $this->name = $name;
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    public function activate(): void
    {
        $this->active = true;
    }

    public function allowOnPurchases(bool $usable): void
    {
        $this->usableOnPurchases = $usable;
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
