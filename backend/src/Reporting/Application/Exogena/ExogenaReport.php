<?php

namespace App\Reporting\Application\Exogena;

use App\Shared\Domain\Money\Money;

/** One exógena format of a taxable year, as the screen and the files show it. */
final readonly class ExogenaReport
{
    /**
     * @param list<ExogenaRow> $rows             reported by name
     * @param list<ExogenaRow> $minorAmountsRows the cuantías menores, one per concept (NIT 222222222)
     */
    public function __construct(
        public int $year,
        public ExogenaFormat $format,
        public Money $threshold,
        public array $rows,
        public array $minorAmountsRows,
        /** Rows (by name or grouped) whose account no concept covers: blank in the file. */
        public int $missingConcepts,
        /** Colombian terceros reported by name without a municipio: the file is refused while any. */
        public int $missingMunicipality,
    ) {
    }
}
