<?php

namespace App\Company\Application\Profile;

use Symfony\Component\Uid\Uuid;

/** The owner edits the company's profile (§4.1). */
final readonly class UpdateCompanyProfile
{
    /**
     * @param list<string> $fiscalResponsibilities FiscalResponsibility values
     */
    public function __construct(
        public Uuid $companyId,
        public Uuid $userId,
        public string $legalName,
        public ?string $tradeName,
        public string $identificationType,
        public string $identificationNumber,
        public ?string $checkDigit,
        public ?string $address,
        public ?string $city,
        public ?string $phone,
        public ?string $email,
        public string $vatRegime,
        public array $fiscalResponsibilities,
        public ?string $defaultChargeTaxId,
        public ?string $defaultWithholdingTaxId,
    ) {
    }
}
