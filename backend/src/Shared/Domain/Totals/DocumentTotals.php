<?php

namespace App\Shared\Domain\Totals;

use App\Shared\Domain\Money\Money;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;

/**
 * The totals of a cotización, factura de venta or factura de compra (§4.6):
 *
 *     Total bruto  = Σ cantidad × valor unitario
 *     Descuentos   = Σ cantidad × valor unitario × % descuento
 *     Subtotal     = Total bruto − Descuentos
 *     Impuestos    = Σ impuesto cargo de cada línea, sobre la base con descuento
 *     Retenciones  = Σ impuesto retención de cada línea (ReteIVA sobre el IVA de la línea)
 *     Total neto   = Subtotal + Impuestos − Retenciones
 *
 * Every line is computed exactly and each sum is rounded once, at document level. Each line then gets its share of
 * the rounded totals by the largest-remainder method, so the line amounts (what a journal entry posts per account)
 * always add up to the document's to the cent.
 */
final readonly class DocumentTotals
{
    /**
     * @param list<LineAmounts> $lines
     */
    private function __construct(
        public Money $gross,
        public Money $discounts,
        public Money $subtotal,
        public Money $taxes,
        public Money $withholdings,
        public Money $net,
        public array $lines,
    ) {
    }

    /**
     * @param list<LineInput> $lines
     */
    public static function of(array $lines): self
    {
        $gross = $discount = $tax = $withholding = [];
        foreach ($lines as $line) {
            $quantity = $line->quantity->toBigDecimal();
            $lineGross = $quantity->multipliedBy($line->unitPrice->toBigDecimal());
            $lineDiscount = $lineGross->multipliedBy($line->discount->fraction());
            $base = $lineGross->minus($lineDiscount);
            $gross[] = $lineGross;
            $discount[] = $lineDiscount;
            $lineTax = $line->charge->on($base, $quantity);
            $tax[] = $lineTax;
            $withholding[] = $line->withholding->on($base, $quantity, $lineTax);
        }

        $grossShares = self::shares($gross);
        $discountShares = self::shares($discount);
        $taxShares = self::shares($tax);
        $withholdingShares = self::shares($withholding);

        $amounts = [];
        foreach (array_keys($lines) as $i) {
            $subtotal = $grossShares[$i]->minus($discountShares[$i]);
            $amounts[] = new LineAmounts(
                $grossShares[$i],
                $discountShares[$i],
                $subtotal,
                $taxShares[$i],
                $withholdingShares[$i],
                $subtotal->plus($taxShares[$i])->minus($withholdingShares[$i]),
            );
        }

        $totalGross = Money::sum(...$grossShares);
        $totalDiscounts = Money::sum(...$discountShares);
        $subtotal = $totalGross->minus($totalDiscounts);
        $taxes = Money::sum(...$taxShares);
        $withholdings = Money::sum(...$withholdingShares);

        return new self($totalGross, $totalDiscounts, $subtotal, $taxes, $withholdings, $subtotal->plus($taxes)->minus($withholdings), $amounts);
    }

    /**
     * Splits the rounded sum of exact values into cents per value: each value rounded down, then the cents left over
     * go one each to the values with the largest remainders (the first one wins a tie).
     *
     * @param list<BigDecimal> $exact
     *
     * @return list<Money>
     */
    private static function shares(array $exact): array
    {
        if ([] === $exact) {
            return [];
        }

        $total = BigDecimal::sum(...$exact)->toScale(Money::SCALE, RoundingMode::HalfUp);
        $floors = array_map(static fn (BigDecimal $v) => $v->toScale(Money::SCALE, RoundingMode::Floor), $exact);
        $left = $total->minus(BigDecimal::sum(...$floors))->multipliedBy(100)->toInt();

        $remainders = array_map(static fn (BigDecimal $v, BigDecimal $f) => $v->minus($f), $exact, $floors);
        $order = array_keys($remainders);
        usort($order, static fn (int $a, int $b) => $remainders[$b]->compareTo($remainders[$a]) ?: $a <=> $b);

        $cent = BigDecimal::of('0.01');
        for ($k = 0; $k < $left; ++$k) {
            $i = $order[$k % \count($order)];
            $floors[$i] = $floors[$i]->plus($cent);
        }

        return array_values(array_map(static fn (BigDecimal $v) => Money::of((string) $v), $floors));
    }
}
