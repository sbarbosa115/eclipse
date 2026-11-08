<?php

namespace App\Reporting\Application\Export;

use App\Reporting\Application\Tax\MonthRange;
use App\Reporting\Application\Tax\TaxReports;
use App\Reporting\Application\Tax\VatRate;
use App\Reporting\Application\Tax\WithholdingGroup;
use App\Reporting\Application\Tax\WithholdingKind;
use App\Reporting\Application\Tax\WithholdingLine;
use App\Reporting\Application\Tax\WithholdingSide;
use App\Shared\Domain\Money\Money;
use Symfony\Component\Uid\Uuid;

/**
 * The tax working papers as exportable tables (observations PRD §4.7 a–c), built from the same TaxReports the screen
 * reads. A working paper keeps its subtotals in line: each month and kind closes with its total, the books' figure and
 * the difference, as the accountant reads it on paper.
 */
final class TaxTables
{
    private const IDENTIFICATION = ['nit' => 'NIT', 'cc' => 'C.C.', 'ce' => 'C.E.', 'pasaporte' => 'Pasaporte', 'ti' => 'T.I.'];

    public function __construct(private readonly TaxReports $reports)
    {
    }

    /** @throws ExportTooLarge */
    public function withholdings(Uuid $companyId, WithholdingSide $side, MonthRange $range, ?WithholdingKind $kind, int $limit): TabularReport
    {
        $report = $this->reports->withholdings($companyId, $side, $range, $kind);
        $multiMonth = \count($range->months()) > 1;
        $size = $report->rowCount() + 3 * \count($report->groups) + ($multiMonth ? 3 * \count($report->totals) : 0);
        if ($size > $limit) {
            throw new ExportTooLarge($size, $limit);
        }

        $rows = [];
        foreach ($report->groups as $group) {
            foreach ($group->rows as $line) {
                $rows[] = self::line($line);
            }
            array_push($rows, ...self::closing((string) $group->month, $group, $side));
        }
        if ($multiMonth) {
            foreach ($report->totals as $total) {
                array_push($rows, ...self::closing('Periodo', $total, $side));
            }
        }

        $subtitle = [$range->label()];
        if (null !== $kind) {
            $subtitle[] = 'Tipo: '.$kind->label();
        }
        if (null !== ($voided = self::voided($report->voidedCount))) {
            $subtitle[] = $voided;
        }

        return new TabularReport(
            $side->title(),
            $side->slug().'-'.$range->slug(),
            [
                new ReportColumn('Mes'),
                new ReportColumn('Tipo de retención'),
                new ReportColumn('Tipo de documento'),
                new ReportColumn('Número'),
                new ReportColumn('DV'),
                new ReportColumn(WithholdingSide::Practiced === $side ? 'Proveedor' : 'Cliente'),
                new ReportColumn('Impuesto'),
                new ReportColumn('Tarifa'),
                new ReportColumn('Base', ColumnKind::Money),
                new ReportColumn('Retención', ColumnKind::Money),
                new ReportColumn('Documentos', ColumnKind::Number),
            ],
            $rows,
            null,
            $subtitle,
        );
    }

    /** @throws ExportTooLarge */
    public function vat(Uuid $companyId, MonthRange $range, int $limit): TabularReport
    {
        $report = $this->reports->vat($companyId, $range);
        $size = \count($report->generated) + \count($report->deductible) + 10;
        if ($size > $limit) {
            throw new ExportTooLarge($size, $limit);
        }

        $rates = static fn (string $concept, array $rates) => array_map(static fn (VatRate $r) => [$concept, self::rate('percentage', $r->rate), $r->base, $r->tax], $rates);
        $base = static fn (array $rates) => Money::sum(...array_map(static fn (VatRate $r) => Money::of($r->base), $rates))->toString();
        $net = $report->net();
        $rows = [
            ...$rates('IVA generado', $report->generated),
            ['Total IVA generado', '', $base($report->generated), $report->generatedTotal->toString()],
            ['Según libros ('.TaxReports::VAT_GENERATED.')', '', '', $report->ledgerGenerated->toString()],
            ['Diferencia', '', '', $report->generatedDifference()->toString()],
            ...$rates('IVA descontable', $report->deductible),
            ['Total IVA descontable', '', $base($report->deductible), $report->deductibleTotal->toString()],
            ['Según libros ('.TaxReports::VAT_DEDUCTIBLE.')', '', '', $report->ledgerDeductible->toString()],
            ['Diferencia', '', '', $report->deductibleDifference()->toString()],
            ['ReteIVA practicada', '', '', $report->reteivaPracticed->toString()],
            ['ReteIVA sufrida', '', '', $report->reteivaSuffered->toString()],
            ['Impoconsumo', '', '', $report->consumptionTax->toString()],
            [$net->isNegative() ? 'Saldo a favor' : 'Saldo a pagar', '', '', ($net->isNegative() ? $net->negated() : $net)->toString()],
        ];

        $subtitle = [$range->label()];
        if (null !== ($voided = self::voided($report->voidedCount))) {
            $subtitle[] = $voided;
        }

        return new TabularReport(
            'IVA',
            'iva-'.$range->slug(),
            [
                new ReportColumn('Concepto'),
                new ReportColumn('Tarifa'),
                new ReportColumn('Base', ColumnKind::Money),
                new ReportColumn('Valor', ColumnKind::Money),
            ],
            $rows,
            null,
            $subtitle,
        );
    }

    /** @return list<string> */
    private static function line(WithholdingLine $l): array
    {
        return [
            $l->month,
            $l->kind->label(),
            self::IDENTIFICATION[$l->identificationType] ?? strtoupper($l->identificationType),
            $l->identificationNumber,
            $l->checkDigit ?? '',
            $l->terceroName,
            $l->taxName,
            self::rate($l->calculation, $l->rate),
            $l->base,
            $l->amount,
            (string) $l->documents,
        ];
    }

    /**
     * The three lines that close a month and kind (or a kind over the period): total, según libros, diferencia.
     *
     * @return list<list<string>>
     */
    private static function closing(string $month, WithholdingGroup $group, WithholdingSide $side): array
    {
        $label = $group->kind->label();

        return [
            [$month, $label, '', '', '', 'Total '.$label, '', '', $group->totalBase->toString(), $group->totalAmount->toString(), ''],
            [$month, $label, '', '', '', 'Según libros ('.$side->ledgerPrefix($group->kind).')', '', '', '', $group->ledgerAmount->toString(), ''],
            [$month, $label, '', '', '', 'Diferencia', '', '', '', $group->difference()->toString(), ''],
        ];
    }

    /** "4 %", "0,966 %"; a per-unit tax shows its value per unit. */
    private static function rate(string $calculation, string $value): string
    {
        $number = str_contains($value, '.') ? rtrim(rtrim($value, '0'), '.') : $value;
        $number = str_replace('.', ',', '' === $number ? '0' : $number);

        return 'percentage' === $calculation ? $number.' %' : '$ '.$number.' por unidad';
    }

    private static function voided(int $count): ?string
    {
        return match ($count) {
            0 => null,
            1 => '1 factura anulada en el periodo no se incluye',
            default => $count.' facturas anuladas en el periodo no se incluyen',
        };
    }
}
