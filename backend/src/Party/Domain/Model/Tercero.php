<?php

namespace App\Party\Domain\Model;

use App\Shared\Domain\Fiscal\FiscalResponsibility;
use App\Shared\Domain\Fiscal\IdentificationType;
use App\Shared\Domain\Fiscal\PersonType;
use App\Shared\Domain\Fiscal\VatRegime;
use App\Shared\Domain\Model\CompanyOwned;
use App\Shared\Domain\Model\References;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * Anyone the company deals with (§4.2, principle 3: one master with roles). Tipo + número de identificación is unique
 * per company. Referenced by a document, it is deactivated, never deleted. Its personal data can be exported and
 * erased on request (Ley 1581 de 2012): erasing blanks the personal fields and keeps the row, since documents name it.
 */
#[ORM\Entity]
#[ORM\Table(name: 'tercero')]
#[ORM\UniqueConstraint(name: 'tercero_identification', columns: ['company_id', 'identification_type', 'identification_number', 'branch_code'])]
#[ORM\Index(name: 'tercero_company_name', columns: ['company_id', 'display_name'])]
class Tercero implements CompanyOwned
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    /** Razón social, or nombres y apellidos: what lists, searches and documents show. */
    #[ORM\Column(length: 200)]
    private string $displayName;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $firstNames = null;

    #[ORM\Column(length: 120, nullable: true)]
    private ?string $lastNames = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $businessName = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $tradeName = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $address = null;

    /** @var list<array{indicative: string, number: string, extension: ?string}> */
    #[ORM\Column(type: Types::JSON)]
    private array $phones = [];

    #[ORM\Column(length: 160, nullable: true)]
    private ?string $billingContactName = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    #[ORM\Column(length: 30, nullable: true)]
    private ?string $mobile = null;

    #[ORM\Column(length: 12, nullable: true)]
    private ?string $postalCode = null;

    #[ORM\Column(length: 16, nullable: true, enumType: VatRegime::class)]
    private ?VatRegime $vatRegime = null;

    #[ORM\Column]
    private bool $billingContactIsPayer = false;

    /** @var list<string> FiscalResponsibility values */
    #[ORM\Column(type: Types::JSON)]
    private array $fiscalResponsibilities = [FiscalResponsibility::NotApplicable->value];

    #[ORM\Column]
    private bool $isClient = false;

    #[ORM\Column]
    private bool $isSupplier = false;

    #[ORM\Column]
    private bool $isEmployee = false;

    #[ORM\Column]
    private bool $isOther = false;

    /** Overrides posting rule "clientes" for this tercero (a 1305xx account). */
    #[ORM\Column(type: 'uuid', nullable: true)]
    #[References('ledger_account')]
    private ?Uuid $receivableAccountId = null;

    /** Overrides posting rule "proveedores" for this tercero (a 2205xx or 2335xx account). */
    #[ORM\Column(type: 'uuid', nullable: true)]
    #[References('ledger_account')]
    private ?Uuid $payableAccountId = null;

    #[ORM\Column]
    private bool $active = true;

    /** When its personal data was erased on request (Ley 1581). */
    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $erasedAt = null;

    /** @var Collection<int, Contact> */
    #[ORM\OneToMany(targetEntity: Contact::class, mappedBy: 'tercero', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['name' => 'ASC'])]
    private Collection $contacts;

    public function __construct(
        #[ORM\Column(type: 'uuid')]
        private Uuid $companyId,
        #[ORM\Column(length: 12, enumType: PersonType::class)]
        private PersonType $personType,
        #[ORM\Column(length: 16, enumType: IdentificationType::class)]
        private IdentificationType $identificationType,
        #[ORM\Column(length: 20)]
        private string $identificationNumber,
        #[ORM\Column(length: 1, nullable: true)]
        private ?string $checkDigit,
        string $displayName,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
        #[ORM\Column(length: 6)]
        private string $branchCode = '0',
    ) {
        $this->id = Uuid::v7();
        $this->displayName = $displayName;
        $this->contacts = new ArrayCollection();
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function companyId(): Uuid
    {
        return $this->companyId;
    }

    public function personType(): PersonType
    {
        return $this->personType;
    }

    public function identificationType(): IdentificationType
    {
        return $this->identificationType;
    }

    public function identificationNumber(): string
    {
        return $this->identificationNumber;
    }

    public function checkDigit(): ?string
    {
        return $this->checkDigit;
    }

    public function branchCode(): string
    {
        return $this->branchCode;
    }

    public function displayName(): string
    {
        return $this->displayName;
    }

    public function firstNames(): ?string
    {
        return $this->firstNames;
    }

    public function lastNames(): ?string
    {
        return $this->lastNames;
    }

    public function businessName(): ?string
    {
        return $this->businessName;
    }

    public function tradeName(): ?string
    {
        return $this->tradeName;
    }

    public function city(): ?string
    {
        return $this->city;
    }

    public function address(): ?string
    {
        return $this->address;
    }

    /** @return list<array{indicative: string, number: string, extension: ?string}> */
    public function phones(): array
    {
        return $this->phones;
    }

    public function billingContactName(): ?string
    {
        return $this->billingContactName;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function mobile(): ?string
    {
        return $this->mobile;
    }

    public function postalCode(): ?string
    {
        return $this->postalCode;
    }

    public function vatRegime(): ?VatRegime
    {
        return $this->vatRegime;
    }

    public function billingContactIsPayer(): bool
    {
        return $this->billingContactIsPayer;
    }

    /** @return list<FiscalResponsibility> */
    public function fiscalResponsibilities(): array
    {
        return array_map(FiscalResponsibility::from(...), $this->fiscalResponsibilities);
    }

    public function isClient(): bool
    {
        return $this->isClient;
    }

    public function isSupplier(): bool
    {
        return $this->isSupplier;
    }

    public function isEmployee(): bool
    {
        return $this->isEmployee;
    }

    public function isOther(): bool
    {
        return $this->isOther;
    }

    public function receivableAccountId(): ?Uuid
    {
        return $this->receivableAccountId;
    }

    public function payableAccountId(): ?Uuid
    {
        return $this->payableAccountId;
    }

    public function isActive(): bool
    {
        return $this->active;
    }

    public function erasedAt(): ?\DateTimeImmutable
    {
        return $this->erasedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /** @return list<Contact> */
    public function contacts(): array
    {
        return $this->contacts->getValues();
    }
}
