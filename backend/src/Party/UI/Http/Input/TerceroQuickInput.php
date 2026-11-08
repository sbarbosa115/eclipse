<?php

namespace App\Party\UI\Http\Input;

use App\Party\Domain\Model\TerceroProfile;
use App\Shared\Domain\Fiscal\FiscalResponsibility;
use App\Shared\Domain\Fiscal\IdentificationType;
use App\Shared\Domain\Fiscal\PersonType;
use Symfony\Component\Validator\Constraints as Assert;

/** What a document form asks to create a tercero on the spot (§4.2 Quick-create). */
final class TerceroQuickInput
{
    #[Assert\Choice(callback: [TerceroInput::class, 'personTypes'])]
    public string $personType = 'persona';

    #[Assert\Choice(callback: [TerceroInput::class, 'identificationTypes'])]
    public string $identificationType = 'cc';

    #[Assert\NotBlank]
    #[Assert\Length(max: 30)]
    public string $identificationNumber = '';

    #[Assert\Length(max: 1)]
    public ?string $checkDigit = null;

    #[Assert\Length(max: 120)]
    public ?string $firstNames = null;

    #[Assert\Length(max: 120)]
    public ?string $lastNames = null;

    #[Assert\Length(max: 200)]
    public ?string $businessName = null;

    #[Assert\NotBlank]
    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    /** @var list<string> */
    #[Assert\NotBlank(message: 'Choose at least one role.')]
    #[Assert\All([new Assert\Choice(choices: TerceroProfile::ROLES)])]
    public array $roles = [];

    /** ISO 3166-1 alpha-2; Colombia when missing (observations PRD §4.3). */
    #[Assert\Regex(pattern: '/^[A-Z]{2}$/', message: 'Choose the country.')]
    public ?string $countryCode = null;

    /** DANE DIVIPOLA code (5 digits), required for Colombia. */
    #[Assert\Regex(pattern: '/^\d{5}$/', message: 'Choose the municipality.')]
    public ?string $municipalityCode = null;

    /** Required when the country is not Colombia. */
    #[Assert\Length(max: 100)]
    public ?string $city = null;

    /** @throws \App\Shared\Domain\Error\InvalidValue */
    public function toProfile(): TerceroProfile
    {
        return new TerceroProfile(
            PersonType::from($this->personType),
            IdentificationType::from($this->identificationType),
            $this->identificationNumber,
            $this->checkDigit,
            '0',
            $this->firstNames,
            $this->lastNames,
            $this->businessName,
            null,
            self::blank($this->city),
            $this->countryCode,
            $this->municipalityCode,
            null,
            [],
            null,
            $this->email,
            null,
            null,
            null,
            false,
            [FiscalResponsibility::NotApplicable],
            $this->roles,
            null,
            null,
        );
    }

    private static function blank(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }
}
