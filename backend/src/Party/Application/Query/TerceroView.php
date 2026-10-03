<?php

namespace App\Party\Application\Query;

/**
 * A tercero as documents read it: to copy its name and identification, to e-mail it, to know its roles and its
 * accounts, and to decide the retenciones the company practices on it (its responsabilidades fiscales).
 */
final readonly class TerceroView
{
    /**
     * @param list<string> $roles                  cliente, proveedor, empleado, otro
     * @param list<string> $fiscalResponsibilities O-13, O-15, O-23, O-47, R-99-PN
     */
    public function __construct(
        public string $id,
        public string $displayName,
        public string $personType,
        public string $identificationType,
        public string $identificationNumber,
        public ?string $checkDigit,
        public ?string $email,
        public ?string $city,
        public ?string $address,
        public array $roles,
        public array $fiscalResponsibilities,
        public ?string $receivableAccountId,
        public ?string $payableAccountId,
        public bool $active,
    ) {
    }
}
