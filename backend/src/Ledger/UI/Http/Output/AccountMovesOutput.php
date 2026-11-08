<?php

namespace App\Ledger\UI\Http\Output;

use App\Ledger\Application\Command\AccountMoves;

/**
 * What pointed at an account that just got its first auxiliar, and moved to it (observations PRD §4.1): the posting
 * rules by concept, and how many taxes, payment methods, products and terceros.
 */
final readonly class AccountMovesOutput
{
    /**
     * @param list<string> $postingRules concepts
     */
    public function __construct(
        public array $postingRules,
        public int $taxes,
        public int $paymentMethods,
        public int $products,
        public int $terceros,
    ) {
    }

    public static function of(AccountMoves $m): self
    {
        return new self($m->postingRules, $m->taxes, $m->paymentMethods, $m->products, $m->terceros);
    }
}
