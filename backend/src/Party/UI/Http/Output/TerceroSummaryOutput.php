<?php

namespace App\Party\UI\Http\Output;

use App\Party\Domain\Model\Tercero;
use App\Shared\Application\Geo\MunicipalityView;

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
        public string $branchCode,
        public ?string $tradeName,
        /** ISO 3166-1 alpha-2 (observations PRD §4.3). */
        public string $countryCode = 'CO',
        /** DANE DIVIPOLA code (5 digits); null until a Colombian tercero is saved again. */
        public ?string $municipalityCode = null,
        public ?string $municipalityName = null,
        public ?string $departmentCode = null,
        public ?string $departmentName = null,
    ) {
    }

    public static function of(Tercero $t, ?MunicipalityView $municipality = null): self
    {
        return new self(
            $t->id()->toRfc4122(),
            $t->displayName(),
            $t->personType()->value,
            $t->identificationType()->value,
            $t->identificationNumber(),
            $t->checkDigit(),
            $t->email(),
            $t->city(),
            self::rolesOf($t),
            $t->isActive(),
            $t->branchCode(),
            $t->tradeName(),
            $t->countryCode(),
            $t->municipalityCode(),
            $municipality?->name,
            $municipality?->departmentCode,
            $municipality?->departmentName,
        );
    }

    /** @return list<string> */
    public static function rolesOf(Tercero $t): array
    {
        return array_keys(array_filter(['cliente' => $t->isClient(), 'proveedor' => $t->isSupplier(), 'empleado' => $t->isEmployee(), 'otro' => $t->isOther()]));
    }
}
