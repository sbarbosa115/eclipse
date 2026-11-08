<?php

namespace App\Reporting\UI\Http;

use App\Reporting\Application\Export\CsvEncoder;
use App\Reporting\Application\Export\ExportFormat;
use App\Reporting\Application\Export\ExportLimits;
use App\Reporting\Application\Export\ExportTooLarge;
use App\Reporting\Application\Export\ReportPdf;
use App\Reporting\Application\Export\TabularReport;
use App\Shared\UI\Http\ApiException;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Uid\Uuid;

/**
 * Any TabularReport as the file the browser saves (§4.13, §9 Q26), so every export endpoint answers the same way:
 * ?format=csv (UTF-8 with BOM, `;` separated, decimal points; streamed) or ?format=pdf (the company's frame); ?check=1
 * only rehearses it (204, or 422 `export_too_large`). A report above the row cap is refused, never cut short.
 */
final class ReportFiles
{
    public function __construct(private readonly ReportPdf $pdf)
    {
    }

    /** The ?format= of the request: csv (default) or pdf, else 400 invalid_format. */
    public static function format(Request $request): ExportFormat
    {
        return ExportFormat::tryFrom($request->query->getString('format', 'csv')) ?? throw ApiException::badRequest('invalid_format', '"format" must be csv or pdf.');
    }

    public static function tooLarge(ExportTooLarge $e): ApiException
    {
        return new ApiException(422, 'export_too_large', \sprintf('The report has about %d rows; one export holds at most %d. Narrow the dates or filters.', $e->rows, $e->limit));
    }

    public function respond(Request $request, Uuid $companyId, TabularReport $report, ExportFormat $format): Response
    {
        if ($request->query->getBoolean('check')) {
            // A rehearsal for the screen: refuses a report above the cap with the same 422, and answers 204 without
            // building the file, so the person is told before the browser starts saving.
            try {
                if (ExportFormat::Pdf === $format) {
                    $rows = 0;
                    foreach ($report->rows as $_) {
                        if (++$rows > ExportLimits::PDF_ROWS) {
                            throw new ExportTooLarge($rows, ExportLimits::PDF_ROWS);
                        }
                    }
                }
            } catch (ExportTooLarge $e) {
                throw self::tooLarge($e);
            }

            return new Response(null, Response::HTTP_NO_CONTENT);
        }

        if (ExportFormat::Pdf === $format) {
            try {
                $bytes = $this->pdf->render($companyId, $report);
            } catch (ExportTooLarge $e) {
                throw self::tooLarge($e);
            }

            return new Response($bytes, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $report->fileName.'.pdf'),
                'Cache-Control' => 'private, no-store',
            ]);
        }

        return new StreamedResponse(static function () use ($report): void {
            foreach (CsvEncoder::chunks($report) as $chunk) {
                echo $chunk;
                flush();
            }
        }, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $report->fileName.'.csv'),
            'Cache-Control' => 'private, no-store',
            'X-Accel-Buffering' => 'no',
        ]);
    }
}
