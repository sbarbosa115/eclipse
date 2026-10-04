<?php

namespace App\Company\Domain\Model;

use App\Shared\Domain\Fiscal\CheckDigit;
use App\Shared\Domain\Fiscal\FiscalResponsibility;
use App\Shared\Domain\Fiscal\IdentificationType;
use App\Shared\Domain\Fiscal\PersonType;
use App\Shared\Domain\Fiscal\VatRegime;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Uid\Uuid;

/**
 * The tenant: everything else belongs to one company (§4.1). Its identity and fiscal data appear on every PDF.
 */
#[ORM\Entity]
#[ORM\Table(name: 'company')]
class Company
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid')]
    private Uuid $id;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $tradeName = null;

    #[ORM\Column(length: 12, enumType: PersonType::class)]
    private PersonType $personType = PersonType::Company;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $address = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $city = null;

    #[ORM\Column(length: 40, nullable: true)]
    private ?string $phone = null;

    #[ORM\Column(length: 180, nullable: true)]
    private ?string $email = null;

    /** The logo's attachment id (Shared Attachment, owner "company"). */
    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $logoId = null;

    #[ORM\Column(length: 16, enumType: VatRegime::class)]
    private VatRegime $vatRegime = VatRegime::Responsible;

    /** @var list<string> FiscalResponsibility values */
    #[ORM\Column(type: Types::JSON)]
    private array $fiscalResponsibilities = [FiscalResponsibility::NotApplicable->value];

    /** Impuesto cargo pre-selected on new products and lines (a Ledger Tax id). */
    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $defaultChargeTaxId = null;

    /** Impuesto retención pre-selected on new products and lines (a Ledger Tax id). */
    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $defaultWithholdingTaxId = null;

    /** Warn the owner when fewer invoice numbers than this remain in the resolution (§4.1). */
    #[ORM\Column]
    private int $resolutionWarningNumbers = 100;

    /** …or fewer days than this before it expires. */
    #[ORM\Column]
    private int $resolutionWarningDays = 30;

    /** The administrator confirmed the company holds the DIAN permission to invoice manually (§4.1). */
    #[ORM\Column(type: 'uuid', nullable: true)]
    private ?Uuid $manualInvoicingConfirmedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $manualInvoicingConfirmedAt = null;

    public function __construct(
        #[ORM\Column(length: 180)]
        private string $legalName,
        #[ORM\Column(length: 12, enumType: IdentificationType::class)]
        private IdentificationType $identificationType,
        #[ORM\Column(length: 20)]
        private string $identificationNumber,
        #[ORM\Column(length: 1, nullable: true)]
        private ?string $checkDigit,
        #[ORM\Column]
        private \DateTimeImmutable $createdAt,
    ) {
        $this->id = Uuid::v7();
    }

    /**
     * The owner edits the profile (§4.1). For a NIT the number is kept as digits and the check digit is computed when
     * it is not given (a given one is kept: it is editable); every other document type has none.
     *
     * @param list<FiscalResponsibility> $responsibilities
     */
    public function updateProfile(
        string $legalName,
        ?string $tradeName,
        IdentificationType $identificationType,
        string $identificationNumber,
        ?string $checkDigit,
        ?string $address,
        ?string $city,
        ?string $phone,
        ?string $email,
        VatRegime $vatRegime,
        array $responsibilities,
        ?Uuid $defaultChargeTaxId,
        ?Uuid $defaultWithholdingTaxId,
    ): void {
        $number = $identificationType->hasCheckDigit() ? (preg_replace('/\D/', '', $identificationNumber) ?? '') : trim($identificationNumber);
        $this->legalName = trim($legalName);
        $this->tradeName = self::blank($tradeName);
        $this->identificationType = $identificationType;
        $this->identificationNumber = $number;
        $this->checkDigit = $identificationType->hasCheckDigit() ? (self::blank($checkDigit) ?? CheckDigit::of($number)) : null;
        $this->personType = IdentificationType::Nit === $identificationType ? PersonType::Company : PersonType::Person;
        $this->address = self::blank($address);
        $this->city = self::blank($city);
        $this->phone = self::blank($phone);
        $this->email = self::blank($email);
        $this->vatRegime = $vatRegime;
        $this->fiscalResponsibilities = array_values(array_unique(array_map(static fn (FiscalResponsibility $r) => $r->value, $responsibilities)));
        $this->defaultChargeTaxId = $defaultChargeTaxId;
        $this->defaultWithholdingTaxId = $defaultWithholdingTaxId;
    }

    /** The attachment id of the logo, or null once removed. */
    public function useLogo(?Uuid $logoId): void
    {
        $this->logoId = $logoId;
    }

    /** The owner says the company holds the DIAN permission to invoice manually (§4.1); logged by the caller. */
    public function confirmManualInvoicing(Uuid $by, \DateTimeImmutable $at): void
    {
        $this->manualInvoicingConfirmedBy = $by;
        $this->manualInvoicingConfirmedAt = $at;
    }

    public function hasConfirmedManualInvoicing(): bool
    {
        return null !== $this->manualInvoicingConfirmedAt;
    }

    /** When to warn the owner that the resolution is running out: fewer numbers or fewer days than these. */
    public function warnResolutionAt(int $numbers, int $days): void
    {
        $this->resolutionWarningNumbers = max(0, $numbers);
        $this->resolutionWarningDays = max(0, $days);
    }

    private static function blank(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }

    public function id(): Uuid
    {
        return $this->id;
    }

    public function legalName(): string
    {
        return $this->legalName;
    }

    public function tradeName(): ?string
    {
        return $this->tradeName;
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

    public function address(): ?string
    {
        return $this->address;
    }

    public function city(): ?string
    {
        return $this->city;
    }

    public function phone(): ?string
    {
        return $this->phone;
    }

    public function email(): ?string
    {
        return $this->email;
    }

    public function logoId(): ?Uuid
    {
        return $this->logoId;
    }

    public function vatRegime(): VatRegime
    {
        return $this->vatRegime;
    }

    /** @return list<FiscalResponsibility> */
    public function fiscalResponsibilities(): array
    {
        return array_map(FiscalResponsibility::from(...), $this->fiscalResponsibilities);
    }

    public function defaultChargeTaxId(): ?Uuid
    {
        return $this->defaultChargeTaxId;
    }

    public function defaultWithholdingTaxId(): ?Uuid
    {
        return $this->defaultWithholdingTaxId;
    }

    public function resolutionWarningNumbers(): int
    {
        return $this->resolutionWarningNumbers;
    }

    public function resolutionWarningDays(): int
    {
        return $this->resolutionWarningDays;
    }

    public function manualInvoicingConfirmedBy(): ?Uuid
    {
        return $this->manualInvoicingConfirmedBy;
    }

    public function manualInvoicingConfirmedAt(): ?\DateTimeImmutable
    {
        return $this->manualInvoicingConfirmedAt;
    }

    public function createdAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }
}
