<?php

namespace App\Company\UI\Http\Input;

use App\Shared\Domain\Fiscal\FiscalResponsibility;
use App\Shared\Domain\Fiscal\IdentificationType;
use App\Shared\Domain\Fiscal\VatRegime;
use Symfony\Component\Validator\Constraints as Assert;

/** The company's profile (§4.1). The NIT's check digit is computed when left empty. */
final class CompanyInput
{
    #[Assert\NotBlank(message: 'Write the razón social.')]
    #[Assert\Length(max: 180)]
    public string $legalName = '';

    #[Assert\Length(max: 180)]
    public ?string $tradeName = null;

    #[Assert\Choice(callback: [self::class, 'identificationTypes'], message: 'Choose a document type.')]
    public string $identificationType = 'nit';

    #[Assert\NotBlank]
    #[Assert\Length(max: 20)]
    public string $identificationNumber = '';

    #[Assert\Regex(pattern: '/^\d$/', message: 'The verification digit is a single digit.')]
    public ?string $checkDigit = null;

    #[Assert\Length(max: 200)]
    public ?string $address = null;

    #[Assert\Length(max: 100)]
    public ?string $city = null;

    #[Assert\Length(max: 40)]
    public ?string $phone = null;

    #[Assert\Email]
    #[Assert\Length(max: 180)]
    public ?string $email = null;

    #[Assert\Choice(callback: [self::class, 'vatRegimes'])]
    public string $vatRegime = 'responsable';

    /** @var list<string> */
    #[Assert\All([new Assert\Choice(callback: [self::class, 'responsibilities'])])]
    public array $fiscalResponsibilities = [];

    #[Assert\Uuid]
    public ?string $defaultChargeTaxId = null;

    #[Assert\Uuid]
    public ?string $defaultWithholdingTaxId = null;

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
    public static function responsibilities(): array
    {
        return array_column(FiscalResponsibility::cases(), 'value');
    }
}
