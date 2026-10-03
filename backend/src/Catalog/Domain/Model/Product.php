<?php

namespace App\Catalog\Domain\Model;

use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Model\References;
use App\Shared\Domain\Money\UnitPrice;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * A product or service of the catalog (§4.3): no stock in stage 1, one sale price (§9 OQ-9). Código unique per
 * company. Its taxes and accounts are the defaults a document line starts from.
 */
#[ORM\Entity]
#[ORM\Table(name: 'product')]
#[ORM\UniqueConstraint(name: 'product_code', columns: ['company_id', 'code'])]
#[ORM\Index(name: 'product_company_name', columns: ['company_id', 'name'])]
class Product implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(type: 'uuid', nullable: true)]
    #[References('product_category', onDelete: 'SET NULL')]
    private ?Uuid $categoryId = null;

    #[ORM\Column(length: 2000, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    private bool $active = true;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(length: 12, enumType: ProductType::class)]
        private ProductType $type,
        #[ORM\Column(length: 40)]
        private string $code,
        #[ORM\Column(length: 200)]
        private string $name,
        /** DIAN unit of measure code (§9 Q21): 94 unidad by default. */
        #[ORM\Column(length: 8)]
        private string $unitCode,
        #[ORM\Column(type: 'unit_price')]
        private UnitPrice $salePrice,
        /** The sale price includes the charge tax (§4.3). */
        #[ORM\Column]
        private bool $priceIncludesTax,
        #[ORM\Column(type: 'uuid', nullable: true)]
        #[References('tax')]
        private ?Uuid $chargeTaxId,
        #[ORM\Column(type: 'uuid', nullable: true)]
        #[References('tax')]
        private ?Uuid $withholdingTaxId,
        /** Overrides posting rule "ingreso". */
        #[ORM\Column(type: 'uuid', nullable: true)]
        #[References('ledger_account')]
        private ?Uuid $revenueAccountId,
        /** Overrides "gasto por defecto" / "compra de mercancías" on purchases. */
        #[ORM\Column(type: 'uuid', nullable: true)]
        #[References('ledger_account')]
        private ?Uuid $expenseAccountId,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
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

    public function type(): ProductType
    {
        return $this->type;
    }

    public function code(): string
    {
        return $this->code;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function description(): ?string
    {
        return $this->description;
    }

    public function categoryId(): ?Uuid
    {
        return $this->categoryId;
    }

    public function unitCode(): string
    {
        return $this->unitCode;
    }

    public function salePrice(): UnitPrice
    {
        return $this->salePrice;
    }

    public function priceIncludesTax(): bool
    {
        return $this->priceIncludesTax;
    }

    public function chargeTaxId(): ?Uuid
    {
        return $this->chargeTaxId;
    }

    public function withholdingTaxId(): ?Uuid
    {
        return $this->withholdingTaxId;
    }

    public function revenueAccountId(): ?Uuid
    {
        return $this->revenueAccountId;
    }

    public function expenseAccountId(): ?Uuid
    {
        return $this->expenseAccountId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
