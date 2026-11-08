<?php

namespace App\Reporting\UI\Http\Controller;

use App\Reporting\Application\Exogena\ExogenaFormat;
use App\Reporting\Application\Exogena\ExogenaReport;
use App\Reporting\Application\Exogena\ExogenaReports;
use App\Reporting\Application\Export\ExogenaTables;
use App\Reporting\Application\Export\ExportLimits;
use App\Reporting\Application\Export\ExportTooLarge;
use App\Reporting\UI\Http\Output\ExogenaOutput;
use App\Reporting\UI\Http\ReportFiles;
use App\Shared\Domain\Calendar;
use App\Shared\Domain\Money\Money;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\Security\Permission;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Reportes › Impuestos › Exógena (observations PRD §4.7 d): the working papers of formats 1001, 1003, 1005, 1006, 1007,
 * 1008 and 1009 for a taxable year, per tercero (and account and DIAN concept in 1001 and 1007), from the emitted
 * invoices of the year and, in 1008/1009, the cartera at 31/12. VIEW_BOOKS.
 *
 * ?year=YYYY (default the year that ended; from 2000 to this year, else 400 `invalid_year`); ?threshold= pesos, the
 * cuantías menores (default 0: everyone by name; a negative or non-numeric one is 400 `invalid_threshold`).
 */
#[Route('/api/v1/reports/taxes/exogena')]
#[IsGranted(Permission::VIEW_BOOKS)]
final class ExogenaController extends AbstractController
{
    private const FORMATS = '1001|1003|1005|1006|1007|1008|1009';

    public function __construct(
        private readonly ExogenaReports $reports,
        private readonly ExogenaTables $tables,
        private readonly ReportFiles $files,
        private readonly Calendar $calendar,
    ) {
    }

    /** The format's rows, its cuantías menores and what keeps it from being filed (missing concepts, municipios). */
    #[Route('/{format}', methods: ['GET'], requirements: ['format' => self::FORMATS])]
    #[ApiResponse(ExogenaOutput::class)]
    public function exogena(string $format, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->json(ExogenaOutput::of($this->report($format, $request, $user)));
    }

    /**
     * The working paper as a file (?format=csv|pdf, ?check=1 to ask first), with the same filters. 422
     * `exogena_terceros_incomplete` while a Colombian tercero reported by name has no municipio (the terceros list
     * filters them with ?missing_municipality=1); 422 `export_too_large` above the cap.
     */
    #[Route('/{format}/export', methods: ['GET'], requirements: ['format' => self::FORMATS])]
    public function export(string $format, Request $request, #[CurrentUser] SignedInUser $user): Response
    {
        $file = ReportFiles::format($request);
        $report = $this->report($format, $request, $user);
        if ($report->missingMunicipality > 0) {
            throw new ApiException(422, 'exogena_terceros_incomplete', \sprintf('%d tercero(s) of the format have no municipality: complete them before exporting.', $report->missingMunicipality));
        }
        try {
            $table = $this->tables->table($report, ExportLimits::forFormat($file));
        } catch (ExportTooLarge $e) {
            throw ReportFiles::tooLarge($e);
        }

        return $this->files->respond($request, $user->companyId(), $table, $file);
    }

    private function report(string $format, Request $request, SignedInUser $user): ExogenaReport
    {
        return $this->reports->report($user->companyId(), ExogenaFormat::from($format), $this->year($request), self::threshold($request));
    }

    private function year(Request $request): int
    {
        $current = (int) $this->calendar->today()->format('Y');
        $value = $request->query->getString('year');
        if ('' === $value) {
            return $current - 1;
        }
        if (1 !== preg_match('/^\d{4}$/', $value) || (int) $value < 2000 || (int) $value > $current) {
            throw ApiException::badRequest('invalid_year', \sprintf('"year" must be a year from 2000 to %d.', $current));
        }

        return (int) $value;
    }

    private static function threshold(Request $request): Money
    {
        $value = $request->query->getString('threshold');
        if ('' === $value) {
            return Money::zero();
        }
        if (1 !== preg_match('/^\d{1,15}(\.\d{1,2})?$/', $value)) {
            throw ApiException::badRequest('invalid_threshold', '"threshold" must be an amount in pesos, 0 or more.');
        }

        return Money::of($value);
    }
}
