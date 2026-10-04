<?php

namespace App\Reporting\UI\Http\Output;

/** The drill-down of one tercero's cartera: its open documents, the soonest due first. */
final readonly class CarteraDocumentsOutput
{
    /**
     * @param list<CarteraDocumentOutput> $items
     */
    public function __construct(
        public string $asOf,
        public string $terceroId,
        public string $terceroName,
        public array $items,
        public string $total,
    ) {
    }
}
