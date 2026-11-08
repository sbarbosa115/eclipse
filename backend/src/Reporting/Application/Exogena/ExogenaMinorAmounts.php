<?php

namespace App\Reporting\Application\Exogena;

use App\Shared\Domain\Money\Money;

/**
 * Cuantías menores (observations PRD §4.7 d): a tercero whose total in the format's first column (the payment, the
 * income, the balance…) is under the threshold, by size, is not reported by name: its rows are added into one row per
 * concept, which the DIAN reads under the pseudo-NIT 222222222. A threshold of 0 reports everyone.
 */
final class ExogenaMinorAmounts
{
    /**
     * @param list<ExogenaRow> $rows
     *
     * @return array{0: list<ExogenaRow>, 1: list<ExogenaRow>} the rows reported by name (in their order), then the
     *                                                         grouped ones (no concept first, then by concept)
     */
    public static function split(array $rows, Money $threshold): array
    {
        if (!$threshold->isPositive()) {
            return [$rows, []];
        }

        $totals = [];
        foreach ($rows as $row) {
            $id = $row->tercero->id ?? '';
            $totals[$id] = ($totals[$id] ?? Money::zero())->plus($row->measure());
        }

        $reported = [];
        /** @var array<string, array{?string, array<string, Money>}> $groups */
        $groups = [];
        foreach ($rows as $row) {
            $total = $totals[$row->tercero->id ?? ''];
            if (!($total->isNegative() ? $total->negated() : $total)->isLessThan($threshold)) {
                $reported[] = $row;
                continue;
            }
            $key = $row->conceptCode ?? '';
            $sums = $groups[$key][1] ?? array_map(static fn () => Money::zero(), $row->values);
            foreach ($row->values as $column => $value) {
                $sums[$column] = ($sums[$column] ?? Money::zero())->plus($value);
            }
            $groups[$key] = [$row->conceptCode, $sums];
        }
        ksort($groups, \SORT_STRING);

        return [$reported, array_values(array_map(static fn (array $g) => new ExogenaRow(null, null, $g[0], $g[1]), $groups))];
    }
}
