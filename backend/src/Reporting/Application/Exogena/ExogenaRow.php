<?php

namespace App\Reporting\Application\Exogena;

use App\Shared\Domain\Money\Money;

/**
 * One row of an exógena format: a tercero (and, in 1001 and 1007, an account and its concept) with the format's values
 * by column key, in the format's column order. Without a tercero it is a cuantías menores row (NIT 222222222).
 */
final readonly class ExogenaRow
{
    /**
     * @param array<string, Money> $values
     */
    public function __construct(
        public ?ExogenaTercero $tercero,
        public ?string $accountCode,
        public ?string $conceptCode,
        public array $values,
    ) {
    }

    /** What decides whether the tercero is a cuantía menor: the format's first column. */
    public function measure(): Money
    {
        return array_values($this->values)[0] ?? Money::zero();
    }
}
