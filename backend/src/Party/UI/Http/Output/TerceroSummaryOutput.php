<?php

namespace App\Party\UI\Http\Output;

/**
 * A tercero in a list or a document's search box.
 */
final readonly class TerceroSummaryOutput
{
    /**
     * @param list<string> $roles cliente, proveedor, empleado, otro
     */
    public function __construct(
        public string $id,
        public string $displayName,
        /** persona or empresa */
        public string $personType,
        /** cc, nit, ce, pasaporte, … */
        public string $identificationType,
        public string $identificationNumber,
        public ?string $checkDigit,
        public ?string $email,
        public ?string $city,
        public array $roles,
        public bool $active,
    ) {
    }
}
