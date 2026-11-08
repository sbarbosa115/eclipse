<?php

namespace App\Reporting\UI\Http\Output;

/**
 * One tercero (and, for 1001 and 1007, account and concept) of an exógena format, with the DIAN identification
 * columns and the format's values by column key (observations PRD §4.7 d).
 */
final readonly class ExogenaRowOutput
{
    /**
     * @param array<string, string> $values amounts by the format's column keys, in value_columns order
     */
    public function __construct(
        public ?string $terceroId,
        /** DIAN document type code (31 NIT, 13 cédula…) */
        public string $documentTypeCode,
        public string $identificationNumber,
        public ?string $checkDigit,
        public ?string $lastNames,
        public ?string $firstNames,
        public ?string $businessName,
        public ?string $address,
        public ?string $departmentCode,
        public ?string $municipalityCode,
        public string $countryCode,
        public ?string $conceptCode,
        public ?string $accountCode,
        public array $values,
    ) {
    }
}
