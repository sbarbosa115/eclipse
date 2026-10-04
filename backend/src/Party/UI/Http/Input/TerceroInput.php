<?php

namespace App\Party\UI\Http\Input;

use App\Party\Domain\Model\ContactDraft;
use App\Party\Domain\Model\TerceroProfile;
use App\Shared\Domain\Fiscal\FiscalResponsibility;
use App\Shared\Domain\Fiscal\IdentificationType;
use App\Shared\Domain\Fiscal\PersonType;
use App\Shared\Domain\Fiscal\VatRegime;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

/** The full tercero form (create and update): the whole record is sent every time. */
final class TerceroInput
{
    #[Assert\Choice(callback: [self::class, 'personTypes'])]
    public string $personType = 'empresa';

    #[Assert\Choice(callback: [self::class, 'identificationTypes'])]
    public string $identificationType = 'nit';

    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    public string $identificationNumber = '';

    /** Only a NIT has one; empty means "compute it". */
    #[Assert\Length(max: 1)]
    public ?string $checkDigit = null;

    #[Assert\Length(max: 6)]
    public string $branchCode = '0';

    #[Assert\Length(max: 120)]
    public ?string $firstNames = null;

    #[Assert\Length(max: 120)]
    public ?string $lastNames = null;

    #[Assert\Length(max: 200)]
    public ?string $businessName = null;

    #[Assert\Length(max: 200)]
    public ?string $tradeName = null;

    #[Assert\Length(max: 100)]
    public ?string $city = null;

    #[Assert\Length(max: 200)]
    public ?string $address = null;

    /** @var list<PhoneInput> */
    #[Assert\Valid]
    #[Assert\Count(max: 10)]
    public array $phones = [];

    #[Assert\Length(max: 160)]
    public ?string $billingContactName = null;

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Length(max: 30)]
    public ?string $mobile = null;

    #[Assert\Length(max: 12)]
    public ?string $postalCode = null;

    #[Assert\Choice(callback: [self::class, 'vatRegimes'])]
    public ?string $vatRegime = null;

    public bool $billingContactIsPayer = false;

    /** @var list<string> */
    #[Assert\All([new Assert\Choice(callback: [self::class, 'fiscalResponsibilities'])])]
    public array $fiscalResponsibilities = [];

    /** @var list<string> */
    #[Assert\All([new Assert\Choice(choices: TerceroProfile::ROLES)])]
    public array $roles = [];

    #[Assert\Uuid]
    public ?string $receivableAccountId = null;

    #[Assert\Uuid]
    public ?string $payableAccountId = null;

    /** @var list<ContactInput> */
    #[Assert\Valid]
    #[Assert\Count(max: 50)]
    public array $contacts = [];

    /** @return list<string> */
    public static function personTypes(): array
    {
        return array_column(PersonType::cases(), 'value');
    }

    /** @return list<string> */
    public static function identificationTypes(): array
    {
        return array_column(IdentificationType::cases(), 'value');
    }

    /** @return list<string> */
    public static function vatRegimes(): array
    {
        return array_column(VatRegime::cases(), 'value');
    }

    /** @return list<string> */
    public static function fiscalResponsibilities(): array
    {
        return array_column(FiscalResponsibility::cases(), 'value');
    }

    /** @throws \App\Shared\Domain\Error\InvalidValue */
    public function toProfile(): TerceroProfile
    {
        return new TerceroProfile(
            PersonType::from($this->personType),
            IdentificationType::from($this->identificationType),
            $this->identificationNumber,
            $this->checkDigit,
            $this->branchCode,
            $this->firstNames,
            $this->lastNames,
            $this->businessName,
            self::blank($this->tradeName),
            self::blank($this->city),
            self::blank($this->address),
            array_map(static fn (PhoneInput $p) => ['indicative' => $p->indicative, 'number' => $p->number, 'extension' => $p->extension], $this->phones),
            self::blank($this->billingContactName),
            self::blank($this->email),
            self::blank($this->mobile),
            self::blank($this->postalCode),
            null === $this->vatRegime ? null : VatRegime::from($this->vatRegime),
            $this->billingContactIsPayer,
            array_map(FiscalResponsibility::from(...), $this->fiscalResponsibilities),
            $this->roles,
            self::uuid($this->receivableAccountId),
            self::uuid($this->payableAccountId),
            array_map(static fn (ContactInput $c) => new ContactDraft(self::uuid($c->id), trim($c->name), self::blank($c->email), self::blank($c->phone)), $this->contacts),
        );
    }

    /** An optional id: null or "" (an empty select) is none. */
    private static function uuid(?string $id): ?Uuid
    {
        return null === $id || '' === $id ? null : Uuid::fromString($id);
    }

    private static function blank(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }
}
