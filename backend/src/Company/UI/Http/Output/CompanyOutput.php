<?php

namespace App\Company\UI\Http\Output;

use App\Company\Application\Query\CompanyView;

/** The company's profile (§4.1). */
final readonly class CompanyOutput
{
    /**
     * @param list<string> $fiscalResponsibilities
     */
    public function __construct(
        public string $id,
        public string $legalName,
        public ?string $tradeName,
        /** nit, cc, ce, pasaporte… (the DIAN's tipos de documento) */
        public string $identificationType,
        public string $identificationNumber,
        public ?string $checkDigit,
        public ?string $address,
        public ?string $city,
        public ?string $phone,
        public ?string $email,
        /** The logo's attachment id; the image itself is GET /company/logo. */
        public ?string $logoId,
        /** responsable, no_responsable or simple */
        public string $vatRegime,
        /** O-13, O-15, O-23, O-47, R-99-PN */
        public array $fiscalResponsibilities,
        public ?string $defaultChargeTaxId,
        public ?string $defaultWithholdingTaxId,
    ) {
    }

    public static function of(CompanyView $c): self
    {
        return new self($c->id, $c->legalName, $c->tradeName, $c->identificationType, $c->identificationNumber, $c->checkDigit, $c->address, $c->city, $c->phone, $c->email, $c->logoId, $c->vatRegime, $c->fiscalResponsibilities, $c->defaultChargeTaxId, $c->defaultWithholdingTaxId);
    }
}
