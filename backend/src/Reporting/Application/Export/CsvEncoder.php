<?php

namespace App\Reporting\Application\Export;

/**
 * A report as CSV for Colombian Excel: UTF-8 with a byte-order mark (so accents open right), `;` between values (the
 * list separator of an es-CO Windows) and CRLF line ends. Numbers stay machine-readable: a decimal POINT, no thousands
 * separator ("1190000.00"), dates as YYYY-MM-DD, so the same file also loads into any other tool.
 */
final class CsvEncoder
{
    public const BOM = "\xEF\xBB\xBF";
    public const SEPARATOR = ';';

    /**
     * @return \Generator<int, string> the file in chunks: the BOM and header first, then a line per row
     */
    public static function chunks(TabularReport $report): \Generator
    {
        yield self::BOM.self::line(array_map(static fn (ReportColumn $c) => $c->label, $report->columns));
        foreach ($report->rows as $row) {
            yield self::line($row);
        }
        $totals = $report->totalsRow();
        if (null !== $totals) {
            yield self::line($totals);
        }
    }

    /** @param list<string> $values */
    public static function line(array $values): string
    {
        return implode(self::SEPARATOR, array_map(self::cell(...), $values))."\r\n";
    }

    /** Quoted when it holds the separator, a quote or a line break; and defused when a spreadsheet would run it as a formula. */
    private static function cell(string $value): string
    {
        // CSV injection: a text that starts like a formula must not run when opened (a tercero named "=HYPERLINK(…)").
        // Numbers (a leading minus sign) are left alone.
        if (1 === preg_match('/^[=+@\t\r]/', $value) || 1 === preg_match('/^-(?!\d+(\.\d+)?$)/', $value)) {
            $value = "'".$value;
        }
        if (1 === preg_match('/[;"\r\n]/', $value)) {
            return '"'.str_replace('"', '""', $value).'"';
        }

        return $value;
    }
}
