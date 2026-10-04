<?php

namespace App\Company\Application\Query;

/**
 * The company as other contexts show it: on PDFs, in e-mails, in the shell's header.
 */
final readonly class CompanyView
{
    /**
     * @param list<string> $fiscalResponsibilities
     */
    public function __construct(
        public string $id,
        public string $legalName,
        public ?string $tradeName,
        public string $identificationType,
        public string $identificationNumber,
        public ?string $checkDigit,
        public ?string $address,
        public ?string $city,
        public ?string $phone,
        public ?string $email,
        public ?string $logoId,
        public string $vatRegime,
        public array $fiscalResponsibilities,
        public ?string $defaultChargeTaxId,
        public ?string $defaultWithholdingTaxId,
        public int $resolutionWarningNumbers = 100,
        public int $resolutionWarningDays = 30,
        /** ATOM date-time of the owner's confirmation of the DIAN permission to invoice manually, or null. */
        public ?string $manualInvoicingConfirmedAt = null,
    ) {
    }
}
