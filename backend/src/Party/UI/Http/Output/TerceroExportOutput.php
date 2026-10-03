<?php

namespace App\Party\UI\Http\Output;

/** The personal data of one tercero, for a Ley 1581 de 2012 request. */
final readonly class TerceroExportOutput
{
    public function __construct(
        /** ISO 8601 date-time of the export */
        public string $exportedAt,
        public TerceroOutput $tercero,
    ) {
    }
}
