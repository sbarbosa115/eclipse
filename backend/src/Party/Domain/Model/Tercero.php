<?php

namespace App\Party\Domain\Model;

use App\Party\Domain\Error\TerceroErased;
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

    /** What an erased tercero is called: documents keep the name they copied, the master keeps only this. */
    public const ERASED_NAME = 'Datos suprimidos';

    public static function register(Uuid $companyId, TerceroProfile $profile, \DateTimeImmutable $now): self
    {
        $tercero = new self($companyId, $profile->personType, $profile->identificationType, $profile->identificationNumber, $profile->checkDigit, $profile->displayName, $now, $profile->branchCode);
        $tercero->revise($profile);

        return $tercero;
    }

    /**
     * Replaces everything the person edits. Contacts are matched by id (documents point at them); the ones left out
     * go away.
     *
     * @throws TerceroErased
     */
    public function revise(TerceroProfile $p): void
    {
        $this->assertNotErased();
        $this->personType = $p->personType;
        $this->identificationType = $p->identificationType;
        $this->identificationNumber = $p->identificationNumber;
        $this->checkDigit = $p->checkDigit;
        $this->branchCode = $p->branchCode;
        $this->displayName = $p->displayName;
        $this->firstNames = $p->firstNames;
        $this->lastNames = $p->lastNames;
        $this->businessName = $p->businessName;
        $this->tradeName = $p->tradeName;
        $this->city = $p->city;
        $this->address = $p->address;
        $this->phones = $p->phones;
        $this->billingContactName = $p->billingContactName;
        $this->email = $p->email;
        $this->mobile = $p->mobile;
        $this->postalCode = $p->postalCode;
        $this->vatRegime = $p->vatRegime;
        $this->billingContactIsPayer = $p->billingContactIsPayer;
        $this->fiscalResponsibilities = array_map(static fn (FiscalResponsibility $r) => $r->value, $p->fiscalResponsibilities);
        $this->isClient = \in_array('cliente', $p->roles, true);
        $this->isSupplier = \in_array('proveedor', $p->roles, true);
        $this->isEmployee = \in_array('empleado', $p->roles, true);
        $this->isOther = \in_array('otro', $p->roles, true);
        $this->receivableAccountId = $p->receivableAccountId;
        $this->payableAccountId = $p->payableAccountId;
        $this->syncContacts($p->contacts);
    }

    public function deactivate(): void
    {
        $this->active = false;
    }

    /** @throws TerceroErased */
    public function reactivate(): void
    {
        $this->assertNotErased();
        $this->active = true;
    }

    /**
     * Ley 1581 de 2012: blanks the personal fields and the contacts and deactivates, keeping the row (documents name
     * it) with its identification, which invoices must carry.
     */
    public function erase(\DateTimeImmutable $now): void
    {
        if (null !== $this->erasedAt) {
            return;
        }
        $this->displayName = self::ERASED_NAME;
        $this->firstNames = $this->lastNames = $this->businessName = $this->tradeName = null;
        $this->city = $this->address = $this->billingContactName = $this->email = $this->mobile = $this->postalCode = null;
        $this->phones = [];
        $this->billingContactIsPayer = false;
        $this->contacts->clear();
        $this->active = false;
        $this->erasedAt = $now;
    }

    /** @param list<ContactDraft> $drafts */
    private function syncContacts(array $drafts): void
    {
        $existing = [];
        foreach ($this->contacts as $contact) {
            $existing[$contact->id()->toRfc4122()] = $contact;
        }
        $keep = [];
        foreach ($drafts as $draft) {
            $contact = null === $draft->id ? null : ($existing[$draft->id->toRfc4122()] ?? null);
            if (null === $contact) {
                $this->contacts->add(new Contact($this, $this->companyId, $draft->name, $draft->email, $draft->phone));
                continue;
            }
            $contact->revise($draft->name, $draft->email, $draft->phone);
            $keep[$contact->id()->toRfc4122()] = true;
        }
        foreach ($existing as $id => $contact) {
            if (!isset($keep[$id])) {
                $this->contacts->removeElement($contact);
            }
        }
    }

    private function assertNotErased(): void
    {
        if (null !== $this->erasedAt) {
            throw new TerceroErased();
        }
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
