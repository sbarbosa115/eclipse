<?php

namespace App\Ledger\UI\Http\Output;

use App\Ledger\Application\Query\PostingRuleView;

final readonly class PostingRuleOutput
{
    /**
     * @param list<string> $allowedPrefixes the PUC codes the concept's account must start with (ingreso: 41)
     */
    public function __construct(
        /** ingreso, descuento_ventas, iva_generado… (PostingConcept) */
        public string $concept,
        public string $accountId,
        public string $accountCode,
        public string $accountName,
        public array $allowedPrefixes,
        /** False when its account got auxiliares and nothing moved: it no longer receives movements (observations PRD §4.1). */
        public bool $accountPostable = true,
    ) {
    }

    public static function of(PostingRuleView $v): self
    {
        return new self($v->concept, $v->accountId, $v->accountCode, $v->accountName, $v->allowedPrefixes);
    }
}
