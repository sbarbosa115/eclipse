<?php

namespace App\Reporting\UI\Http\Output;

use App\Reporting\Application\Exogena\ExogenaRow;
use App\Shared\Domain\Money\Money;

/**
 * One tercero (and, for 1001 and 1007, account and concept) of an exógena format, with the DIAN identification
 * columns and the format's values by column key (observations PRD §4.7 d). A cuantías menores row has no tercero:
 * document type 43, number 222222222.
 */
final readonly class ExogenaRowOutput
{
    public const MINOR_AMOUNTS_NIT = '222222222';

    /**
     * @param array<string, string> $values amounts by the format's column keys, in value_columns order
     */
    public function __construct(
        public ?string $terceroId,
        /** DIAN document type code (31 NIT, 13 cédula, 43 cuantías menores…) */
        public string $documentTypeCode,
        public string $identificationNumber,
        public ?string $checkDigit,
        /** A person's apellidos (null for a company). */
        public ?string $lastNames,
        /** A person's nombres (null for a company). */
        public ?string $firstNames,
        /** A company's razón social. */
        public ?string $businessName,
        public ?string $address,
        public ?string $departmentCode,
        public ?string $municipalityCode,
        /** ISO 3166-1 alpha-2 */
        public string $countryCode,
        public ?string $conceptCode,
        public ?string $accountCode,
        public array $values,
    ) {
    }

    public static function of(ExogenaRow $row): self
    {
        $values = array_map(static fn (Money $m) => $m->toString(), $row->values);
        $t = $row->tercero;
        if (null === $t) {
            return new self(null, '43', self::MINOR_AMOUNTS_NIT, null, null, null, 'Cuantías menores', null, null, null, 'CO', $row->conceptCode, null, $values);
        }

        return new self(
            $t->id,
            $t->documentTypeCode(),
            $t->identificationNumber,
            $t->checkDigit,
            $t->lastNames,
            $t->firstNames,
            $t->businessName,
            $t->address,
            $t->departmentCode(),
            $t->municipalityCode,
            $t->countryCode,
            $row->conceptCode,
            $row->accountCode,
            $values,
        );
    }
}
