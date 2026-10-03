<?php

namespace App\Ledger\Application\Report;

/**
 * The basic estado de resultados of a period (§9 Q25), from the balance de prueba's movements: ingresos (class 4,
 * net of 4175), costos de ventas (6) and de producción (7), gastos (5). Amounts are positive on each section's own
 * side.
 */
final readonly class IncomeStatement
{
    /**
     * @param list<StatementSection> $sections 4, 6, 7, 5
     */
    public function __construct(
        public string $from,
        public string $to,
        public array $sections,
        public string $revenue,
        /** Classes 6 and 7. */
        public string $costs,
        public string $expenses,
        /** Revenue − costs. */
        public string $grossProfit,
        /** Revenue − costs − expenses. */
        public string $netIncome,
    ) {
    }
}
