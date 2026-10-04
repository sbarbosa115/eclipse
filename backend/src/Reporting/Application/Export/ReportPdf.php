<?php

namespace App\Reporting\Application\Export;

use App\Company\Application\Query\Companies;
use App\Company\Application\Query\LogoReader;
use App\Shared\Application\Port\PdfRenderer;
use App\Shared\Domain\Error\NotFound;
use Symfony\Component\Uid\Uuid;

/** Any TabularReport as a PDF, in the company's frame (header, logo): one template for every report. */
final class ReportPdf
{
    public const TEMPLATE = 'pdf/reports/table.html.twig';

    public function __construct(
        private readonly Companies $companies,
        private readonly LogoReader $logos,
        private readonly PdfRenderer $renderer,
    ) {
    }

    /** @throws ExportTooLarge */
    public function render(Uuid $companyId, TabularReport $report): string
    {
        $rows = [];
        foreach ($report->rows as $row) {
            $rows[] = self::print($report->columns, $row);
            if (\count($rows) > ExportLimits::PDF_ROWS) {
                throw new ExportTooLarge(\count($rows), ExportLimits::PDF_ROWS);
            }
        }
        $totals = $report->totalsRow();

        return $this->renderer->render(self::TEMPLATE, [
            'company' => $this->companies->view($companyId),
            'logo_data_uri' => $this->logo($companyId),
            'title' => $report->title,
            'subtitle' => $report->subtitle,
            'columns' => array_map(static fn (ReportColumn $c) => ['label' => $c->label, 'numeric' => $c->kind->isNumeric()], $report->columns),
            'rows' => $rows,
            'totals' => null === $totals ? null : self::print($report->columns, $totals),
        ]);
    }

    /**
     * @param list<ReportColumn> $columns
     * @param list<string>       $row
     *
     * @return list<string>
     */
    private static function print(array $columns, array $row): array
    {
        $printed = [];
        foreach ($columns as $i => $column) {
            $printed[] = self::format($column->kind, $row[$i] ?? '');
        }

        return $printed;
    }

    private static function format(ColumnKind $kind, string $value): string
    {
        if ('' === $value) {
            return '';
        }

        return match ($kind) {
            ColumnKind::Money => self::money($value),
            ColumnKind::Date => 1 === preg_match('/^(\d{4})-(\d{2})-(\d{2})$/', $value, $m) ? "$m[3]/$m[2]/$m[1]" : $value,
            default => $value,
        };
    }

    /** $ 1.190.000,00 */
    private static function money(string $amount): string
    {
        $negative = str_starts_with($amount, '-');
        [$int, $dec] = array_pad(explode('.', ltrim($amount, '-')), 2, '00');

        return ($negative ? '-' : '').'$ '.number_format((int) $int, 0, ',', '.').','.$dec;
    }

    private function logo(Uuid $companyId): ?string
    {
        try {
            $logo = $this->logos->logo($companyId);
        } catch (NotFound) {
            return null;
        }
        $bytes = @file_get_contents($logo->path);

        return false === $bytes ? null : 'data:'.$logo->contentType.';base64,'.base64_encode($bytes);
    }
}
