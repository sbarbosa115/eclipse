<?php

namespace App\Ledger\Application\Posting;

use App\Shared\Domain\Accounting\PostingConcept;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * One movement a document asks to post: a side, an amount and where it goes, either a concept (resolved through the
 * company's posting rules, or the tercero's own account for clientes/proveedores) or an account chosen explicitly (a
 * payment method's, a product's revenue account, a purchase line's expense account). A zero amount is skipped.
 */
final readonly class EntryLine
{
    private function __construct(
        public Side $side,
        public Money $amount,
        public ?PostingConcept $concept,
        public ?Uuid $accountId,
        public ?Uuid $terceroId,
        public ?string $description,
    ) {
    }

    public static function toConcept(Side $side, Money $amount, PostingConcept $concept, ?Uuid $terceroId = null, ?string $description = null): self
    {
        return new self($side, $amount, $concept, null, $terceroId, $description);
    }

    public static function toAccount(Side $side, Money $amount, Uuid $accountId, ?Uuid $terceroId = null, ?string $description = null): self
    {
        return new self($side, $amount, null, $accountId, $terceroId, $description);
    }
}
