<?php

namespace App\Ledger\UI\Http\Output;

use App\Ledger\Application\Report\StatementSection;

final readonly class StatementSectionOutput
{
    /**
     * @param list<StatementLineOutput> $lines
     */
    public function __construct(
        /** The PUC class: 1 activo, 2 pasivo, 3 patrimonio, 4 ingresos, 5 gastos, 6 costos de ventas, 7 costos de producción. */
        public string $code,
        public string $name,
        public string $total,
        public array $lines,
    ) {
    }

    public static function of(StatementSection $s): self
    {
        return new self($s->code, $s->name, $s->total, array_map(StatementLineOutput::of(...), $s->lines));
    }
}
