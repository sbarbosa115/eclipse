<?php

namespace App\Reporting\UI\Http\Output;

/** Cartera by tercero as of a date: a page of terceros and the grand total. */
final readonly class CarteraOutput
{
    /**
     * @param list<CarteraRowOutput> $items
     */
    public function __construct(
        /** YYYY-MM-DD */
        public string $asOf,
        public array $items,
        /** Terceros with a balance matching the search. */
        public int $total,
        public int $page,
        public int $perPage,
        public CarteraTotalsOutput $totals,
    ) {
    }
}
