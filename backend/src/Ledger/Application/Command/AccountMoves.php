<?php

namespace App\Ledger\Application\Command;

/**
 * What pointed at an account when it got its first auxiliar, and moved to the auxiliar (observations PRD §4.1).
 */
final readonly class AccountMoves
{
    /**
     * @param list<string> $postingRules the concepts (ingreso…), in the order of PostingConcept
     */
    public function __construct(
        public array $postingRules,
        public int $taxes,
        public int $paymentMethods,
        public int $products,
        public int $terceros,
    ) {
    }
}
