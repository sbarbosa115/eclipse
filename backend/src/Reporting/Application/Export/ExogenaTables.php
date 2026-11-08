<?php

namespace App\Reporting\Application\Export;

use App\Reporting\Application\Exogena\ExogenaReport;
use App\Reporting\Application\Exogena\ExogenaRow;
use App\Shared\Domain\Money\Money;

/**
 * An exógena working paper as an exportable table (observations PRD §4.7 d), from the same ExogenaReport the screen
 * reads: the DIAN identification columns in the formats' order, then the format's values, then the cuantías menores
 * rows (222222222). Not the DIAN's XML: the accountant files from it (§9 Q-E).
 */
final class ExogenaTables
{
    /** The DIAN's country code of Colombia; a foreign tercero keeps its ISO code (README, known gaps). */
    private const COLOMBIA = '169';

    /** @throws ExportTooLarge */
    public function table(ExogenaReport $report, int $limit): TabularReport
    {
        $size = \count($report->rows) + \count($report->minorAmountsRows);
        if ($size > $limit) {
            throw new ExportTooLarge($size, $limit);
        }
        $format = $report->format;
        $concepts = $format->hasConcepts();

        $columns = [];
        if ($concepts) {
            $columns[] = new ReportColumn('Concepto');
            $columns[] = new ReportColumn('Cuenta');
        }
        foreach (['Tipo de documento', 'Número de identificación', 'DV', 'Primer apellido y segundo apellido', 'Nombres', 'Razón social', 'Dirección', 'Código departamento', 'Código municipio', 'País'] as $label) {
            $columns[] = new ReportColumn($label);
        }
        foreach ($format->columns() as $label) {
            $columns[] = new ReportColumn($label, ColumnKind::Money);
        }

        $rows = array_map(static fn (ExogenaRow $row) => self::row($row, $concepts), [...$report->rows, ...$report->minorAmountsRows]);

        $subtitle = ['Año gravable '.$report->year];
        if ($report->threshold->isPositive()) {
            $subtitle[] = 'Cuantías menores: terceros por debajo de '.self::pesos($report->threshold).', agrupados con el NIT 222222222';
        }
        if ($report->missingConcepts > 0) {
            $subtitle[] = 1 === $report->missingConcepts
                ? 'Atención: 1 fila no tiene concepto DIAN (Configuración › Exógena)'
                : \sprintf('Atención: %d filas no tienen concepto DIAN (Configuración › Exógena)', $report->missingConcepts);
        }

        $totals = [...array_fill(0, \count($columns) - \count($format->columns()), ''), ...array_values(array_map(static fn (Money $m) => $m->toString(), $report->totals()))];
        $totals[0] = 'Total';

        return new TabularReport($format->title(), \sprintf('exogena-%s-%d', $format->value, $report->year), $columns, $rows, static fn () => $totals, $subtitle);
    }

    /** @return list<string> */
    private static function row(ExogenaRow $row, bool $concepts): array
    {
        $t = $row->tercero;
        $cells = $concepts ? [$row->conceptCode ?? '', $row->accountCode ?? ''] : [];
        $cells = null === $t
            ? [...$cells, '43', '222222222', '', '', '', 'Cuantías menores', '', '', '', self::COLOMBIA]
            : [
                ...$cells,
                $t->documentTypeCode(),
                $t->identificationNumber,
                $t->checkDigit ?? '',
                $t->lastNames ?? '',
                $t->firstNames ?? '',
                $t->businessName ?? '',
                $t->address ?? '',
                $t->departmentCode() ?? '',
                $t->municipalityCode ?? '',
                'CO' === $t->countryCode ? self::COLOMBIA : $t->countryCode,
            ];

        return [...$cells, ...array_values(array_map(static fn (Money $m) => $m->toString(), $row->values))];
    }

    /** "$ 1.000.000,00", for the subtitle a person reads. */
    private static function pesos(Money $amount): string
    {
        [$integer, $cents] = explode('.', $amount->toString()) + [1 => '00'];

        return '$ '.strrev(implode('.', str_split(strrev($integer), 3))).','.$cents;
    }
}
