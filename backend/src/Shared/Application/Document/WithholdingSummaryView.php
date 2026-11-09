<?php

namespace App\Shared\Application\Document;

use App\Shared\Domain\Money\Money;

/**
 * A document's retenciones by tax, for its totals panel and PDF (observations PRD §4.2): name, rate, base and value.
 * Their values add up to the document's Retenciones.
 */
final readonly class WithholdingSummaryView
{
    public function __construct(
        public ?string $taxId,
        public string $name,
        public string $kind,
        public string $rate,
        public string $base,
        public string $amount,
    ) {
    }

    /**
     * @param iterable<list<LineWithholdingView>> $lines each line's retenciones, in line order
     *
     * @return list<self> in the order each tax first appears
     */
    public static function of(iterable $lines): array
    {
        /** @var array<string, array{LineWithholdingView, Money, Money}> $groups */
        $groups = [];
        foreach ($lines as $withholdings) {
            foreach ($withholdings as $w) {
                $key = ($w->taxId ?? '').'|'.$w->name.'|'.$w->rate;
                [$first, $base, $amount] = $groups[$key] ?? [$w, Money::zero(), Money::zero()];
                $groups[$key] = [$first, $base->plus(Money::of($w->baseAmount)), $amount->plus(Money::of($w->amount))];
            }
        }

        return array_values(array_map(static fn (array $g) => new self($g[0]->taxId, $g[0]->name, $g[0]->kind, $g[0]->rate, $g[1]->toString(), $g[2]->toString()), $groups));
    }
}
