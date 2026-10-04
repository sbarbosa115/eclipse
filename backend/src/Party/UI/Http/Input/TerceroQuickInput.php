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
            null,
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
}
