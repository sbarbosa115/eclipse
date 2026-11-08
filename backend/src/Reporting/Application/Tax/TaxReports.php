<?php

namespace App\Reporting\Application\Tax;

use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * Reportes › Impuestos (observations PRD §4.7 a–c): the retenciones and IVA working papers, built from the documents
 * (TaxQueries) with the books' figure next to them (BooksFigures, from the ledger's own query service). The screen and
 * the exports both read these, so a file always shows what the screen shows.
 */
final class TaxReports
{
    /** The IVA accounts of the seeded chart (MustangChart): generado and descontable, with any auxiliares under them. */
    public const VAT_GENERATED = '240805';
    public const VAT_DEDUCTIBLE = '240810';

    public function __construct(
        private readonly TaxQueries $queries,
        private readonly BooksFigures $books,
    ) {
    }

    /**
     * Grouped by month, then kind. A month and kind appear when the documents have rows for it or the books moved its
     * accounts (a difference worth seeing); the per-kind totals cover the whole range.
     */
    public function withholdings(Uuid $companyId, WithholdingSide $side, MonthRange $range, ?WithholdingKind $kind): WithholdingReport
    {
        $kinds = WithholdingKind::covering($kind);
        $prefixes = array_map($side->ledgerPrefix(...), $kinds);

        $byMonth = [];
        foreach ($this->queries->withholdings($companyId, $side, $range, $kinds) as $line) {
            $byMonth[$line->month][$line->kind->value][] = $line;
        }

        $groups = [];
        $sums = array_map(static fn () => [Money::zero(), Money::zero(), Money::zero()], $kinds);
        foreach ($range->months() as $month) {
            $ledger = $this->books->moved($companyId, MonthRange::single($month), $prefixes, $side->growsOnCredit());
            foreach ($kinds as $i => $k) {
                $rows = $byMonth[$month][$k->value] ?? [];
                if ([] === $rows && $ledger[$i]->isZero()) {
                    continue;
                }
                $group = new WithholdingGroup(
                    $month,
                    $k,
                    $rows,
                    Money::sum(...array_map(static fn (WithholdingLine $l) => Money::of($l->base), $rows)),
                    Money::sum(...array_map(static fn (WithholdingLine $l) => Money::of($l->amount), $rows)),
                    $ledger[$i],
                );
                $groups[] = $group;
                $sums[$i] = [$sums[$i][0]->plus($group->totalBase), $sums[$i][1]->plus($group->totalAmount), $sums[$i][2]->plus($group->ledgerAmount)];
            }
        }

        $totals = [];
        foreach ($kinds as $i => $k) {
            $totals[] = new WithholdingGroup(null, $k, [], $sums[$i][0], $sums[$i][1], $sums[$i][2]);
        }

        return new WithholdingReport($side, $range, $kind, $groups, $totals, $this->queries->voidedWithWithholdings($companyId, $side, $range, $kinds));
    }

    public function vat(Uuid $companyId, MonthRange $range): VatReport
    {
        $generated = $this->queries->vatByRate($companyId, true, $range);
        $deductible = $this->queries->vatByRate($companyId, false, $range);
        [$ledgerGenerated] = $this->books->moved($companyId, $range, [self::VAT_GENERATED], true);
        [$ledgerDeductible] = $this->books->moved($companyId, $range, [self::VAT_DEDUCTIBLE], false);

        return new VatReport(
            $range,
            $generated,
            $deductible,
            self::taxOf($generated),
            self::taxOf($deductible),
            Money::of($this->queries->withholdingTotal($companyId, WithholdingSide::Practiced, WithholdingKind::Vat, $range)),
            Money::of($this->queries->withholdingTotal($companyId, WithholdingSide::Suffered, WithholdingKind::Vat, $range)),
            Money::of($this->queries->consumptionTax($companyId, $range)),
            $ledgerGenerated,
            $ledgerDeductible,
            $this->queries->voidedInvoices($companyId, $range),
        );
    }

    /** @param list<VatRate> $rates */
    private static function taxOf(array $rates): Money
    {
        return Money::sum(...array_map(static fn (VatRate $r) => Money::of($r->tax), $rates));
    }
}
