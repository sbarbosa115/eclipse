<?php

namespace App\Party\Domain\Model;

use App\Party\Domain\Error\InvalidTerceroValue;
use App\Shared\Domain\Fiscal\FiscalResponsibility;
use App\Shared\Domain\Fiscal\IdentificationType;
use App\Shared\Domain\Fiscal\PersonType;
use App\Shared\Domain\Fiscal\VatRegime;
use Symfony\Component\Uid\Uuid;

/**
 * Everything a person edits about a tercero (§4.2), checked and normalised once: the identification without
 * punctuation, the DV computed for a NIT, the name that lists and documents show, at least one role.
 */
final readonly class TerceroProfile
{
    public const ROLES = ['cliente', 'proveedor', 'empleado', 'otro'];

    public string $identificationNumber;
    public ?string $checkDigit;
    public string $branchCode;
    public string $displayName;
    public ?string $firstNames;
    public ?string $lastNames;
    public ?string $businessName;
    /** @var list<string> */
    public array $roles;
    /** @var list<FiscalResponsibility> */
    public array $fiscalResponsibilities;
    /** @var list<array{indicative: string, number: string, extension: ?string}> */
    public array $phones;

    /**
     * @param list<string>                                                           $roles
     * @param list<FiscalResponsibility>                                             $fiscalResponsibilities
     * @param list<array{indicative?: ?string, number: string, extension?: ?string}> $phones
     * @param list<ContactDraft>                                                     $contacts
     */
    public function __construct(
        public PersonType $personType,
        public IdentificationType $identificationType,
        string $identificationNumber,
        ?string $checkDigit,
        string $branchCode,
        ?string $firstNames,
        ?string $lastNames,
        ?string $businessName,
        public ?string $tradeName,
        public ?string $city,
        public ?string $address,
        array $phones,
        public ?string $billingContactName,
        public ?string $email,
        public ?string $mobile,
        public ?string $postalCode,
        public ?VatRegime $vatRegime,
        public bool $billingContactIsPayer,
        array $fiscalResponsibilities,
        array $roles,
        public ?Uuid $receivableAccountId,
        public ?Uuid $payableAccountId,
        public array $contacts = [],
    ) {
        $number = Identification::normalize($identificationNumber);
        if (1 !== preg_match('/^[A-Z0-9]{3,20}$/', $number)) {
            throw new InvalidTerceroValue('identification_number', 'Write the identification with letters and digits only.');
        }
        $this->identificationNumber = $number;
        $this->checkDigit = Identification::checkDigit($identificationType, $number, $checkDigit);

        $branch = trim($branchCode);
        if (1 !== preg_match('/^\d{1,6}$/', '' === $branch ? '0' : $branch)) {
            throw new InvalidTerceroValue('branch_code', 'The branch code is a number.');
        }
        $this->branchCode = '' === $branch ? '0' : $branch;

        $this->firstNames = self::blankToNull($firstNames);
        $this->lastNames = self::blankToNull($lastNames);
        $this->businessName = self::blankToNull($businessName);
        $this->displayName = $this->nameFor($personType);

        $unknown = array_diff($roles, self::ROLES);
        if ([] !== $unknown) {
            throw new InvalidTerceroValue('roles', 'Choose a valid role.');
        }
        $roles = array_values(array_unique($roles));
        if ([] === $roles) {
            throw new InvalidTerceroValue('roles', 'Choose at least one role.');
        }
        $this->roles = $roles;

        $this->fiscalResponsibilities = [] === $fiscalResponsibilities ? [FiscalResponsibility::NotApplicable] : array_values(array_unique($fiscalResponsibilities, \SORT_REGULAR));

        $clean = [];
        foreach ($phones as $phone) {
            $number = trim($phone['number']);
            if ('' === $number) {
                continue;
            }
            $clean[] = ['indicative' => self::blankToNull($phone['indicative'] ?? null) ?? '57', 'number' => $number, 'extension' => self::blankToNull($phone['extension'] ?? null)];
        }
        $this->phones = $clean;
    }

    private function nameFor(PersonType $type): string
    {
        if (PersonType::Company === $type) {
            return $this->businessName ?? throw new InvalidTerceroValue('business_name', 'Write the razón social.');
        }
        $name = trim(($this->firstNames ?? '').' '.($this->lastNames ?? ''));

        return '' === $name ? throw new InvalidTerceroValue('first_names', 'Write the name.') : $name;
    }

    private static function blankToNull(?string $value): ?string
    {
        $value = null === $value ? null : trim($value);

        return '' === $value ? null : $value;
    }
}
