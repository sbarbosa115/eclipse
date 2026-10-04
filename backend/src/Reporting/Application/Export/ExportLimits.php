<?php

namespace App\Reporting\Application\Export;

/**
 * How much one export may hold. Above the cap the export is refused (422 `export_too_large`) instead of cut short: a
 * report that silently stops halfway is worse than none. The person narrows the dates, the account or the tercero.
 *
 * A CSV streams (memory stays flat whatever its size), so its cap only protects the request's time; a PDF is built in
 * memory by dompdf, so its cap is far lower.
 */
final class ExportLimits
{
    /** Rows of a CSV: a libro diario counts one row per entry line (an entry has at least two). */
    public const CSV_ROWS = 50000;

    /** Rows of a PDF. */
    public const PDF_ROWS = 1500;

    public static function forFormat(ExportFormat $format): int
    {
        return ExportFormat::Csv === $format ? self::CSV_ROWS : self::PDF_ROWS;
    }

    private function __construct()
    {
    }
}
