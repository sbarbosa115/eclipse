<?php

namespace App\Ledger\UI\Http\Output;

/** A picker's page of postable accounts and how many matched in all ("Mostrando 50 de 61", observations PRD §4.1). */
final readonly class AccountSearchOutput
{
    /**
     * @param list<AccountOutput> $items
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
