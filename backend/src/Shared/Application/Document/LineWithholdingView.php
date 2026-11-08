<?php

namespace App\Shared\Application\Document;

use App\Shared\Domain\Model\CommercialLine;
use App\Shared\Domain\Model\LineWithholding;

/** One retención of a document line as it was copied, with its base and its share (observations PRD §4.2). */
final readonly class LineWithholdingView
{
    public function __construct(
        public ?string $taxId,
        public string $name,
        /** retefuente, reteiva, reteica */
        public string $kind,
        public string $calculation,
        /** A percentage ("2.5000") or a value per unit. */
        public string $rate,
        public ?string $accountId,
        public string $baseAmount,
        public string $amount,
    ) {
    }

    /** @return list<self> */
    public static function ofLine(CommercialLine $line): array
    {
        return array_map(static fn (LineWithholding $w) => new self(
            $w->tax()->taxId()?->toRfc4122(),
            $w->tax()->name(),
            $w->tax()->kind(),
            $w->tax()->calculation()->value,
            $w->tax()->value(),
            $w->tax()->accountId()?->toRfc4122(),
            $w->baseAmount()->toString(),
            $w->amount()->toString(),
        ), $line->withholdings());
    }
}
