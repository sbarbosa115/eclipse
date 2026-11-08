<?php

namespace App\Reporting\Application\Exogena;

/** A tercero as an exógena format identifies it (observations PRD §4.7 d): document, names and address. */
final readonly class ExogenaTercero
{
    /** Mustang's identification types → the DIAN's "tipo de documento" codes of the exógena formats. */
    private const DOCUMENT_TYPES = [
        'rc' => '11',
        'ti' => '12',
        'cc' => '13',
        'te' => '21',
        'ce' => '22',
        'nit' => '31',
        'pasaporte' => '41',
        'die' => '42',
        'pep' => '47',
        'ppt' => '48',
        'nit_extranjero' => '50',
        'nuip' => '91',
    ];

    public function __construct(
        public string $id,
        public string $identificationType,
        public string $identificationNumber,
        public ?string $checkDigit,
        /** persona or empresa */
        public string $personType,
        public ?string $firstNames,
        public ?string $lastNames,
        public ?string $businessName,
        public ?string $address,
        public ?string $municipalityCode,
        public string $countryCode,
    ) {
    }

    public function documentTypeCode(): string
    {
        return self::DOCUMENT_TYPES[$this->identificationType] ?? '43';
    }

    /** DIVIPOLA's department is the first two digits of the municipality's code. */
    public function departmentCode(): ?string
    {
        return null === $this->municipalityCode ? null : substr($this->municipalityCode, 0, 2);
    }

    /** A Colombian tercero must have its municipio to be reported (§4.3); a foreign one has none. */
    public function lacksMunicipality(): bool
    {
        return 'CO' === $this->countryCode && null === $this->municipalityCode;
    }

    /** The name a person reads: the business name, else "apellidos nombres". */
    public function name(): string
    {
        return $this->businessName ?? trim(($this->lastNames ?? '').' '.($this->firstNames ?? ''));
    }
}
