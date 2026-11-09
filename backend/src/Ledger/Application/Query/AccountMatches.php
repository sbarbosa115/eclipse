<?php

namespace App\Ledger\Application\Query;

/** A picker's page of postable accounts and how many matched in all ("Mostrando 50 de 61"). */
final readonly class AccountMatches
{
    /**
     * @param list<AccountView> $items in code order
     */
    public function __construct(
        public array $items,
        public int $total,
    ) {
    }
}
