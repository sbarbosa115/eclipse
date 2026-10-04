<?php

namespace App\Ledger\Application\Report;

/** One class of the PUC in a statement (Ingresos, Gastos, Activo…): its total and its groups and cuentas. */
final readonly class StatementSection
{
    /**
     * @param list<StatementLine> $lines groups and cuentas with an amount, in code order
     */
    public function __construct(
        public string $code,
        public string $name,
        public string $total,
        public array $lines,
    ) {
    }
}
