<?php

namespace App\Ledger\Application\Report;

/**
 * The basic balance general at a date (§9 Q25), from the balance de prueba's closing balances: activo (class 1),
 * pasivo (2) and patrimonio (3). Stage 1 has no closing entries, so the result of classes 4 to 7 so far is shown as
 * the ejercicio's result inside patrimonio.
 */
final readonly class BalanceSheet
{
    /**
     * @param list<StatementSection> $sections 1, 2, 3
     */
    public function __construct(
        public string $date,
        public array $sections,
        public string $currentEarnings,
        public string $totalAssets,
        public string $totalLiabilities,
        /** Class 3 plus the current earnings. */
        public string $totalEquity,
        public bool $balanced,
    ) {
    }
}
