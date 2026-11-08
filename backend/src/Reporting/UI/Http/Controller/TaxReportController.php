<?php

namespace App\Reporting\UI\Http\Controller;

use App\Reporting\Application\Export\ExportLimits;
use App\Reporting\Application\Export\ExportTooLarge;
use App\Reporting\Application\Export\TaxTables;
use App\Reporting\Application\Tax\MonthRange;
use App\Reporting\Application\Tax\TaxReports;
use App\Reporting\Application\Tax\WithholdingKind;
use App\Reporting\Application\Tax\WithholdingSide;
use App\Reporting\UI\Http\Output\VatReportOutput;
use App\Reporting\UI\Http\Output\WithholdingReportOutput;
use App\Reporting\UI\Http\ReportFiles;
use App\Shared\Domain\Calendar;
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
 * Reportes › Impuestos (observations PRD §4.7): retenciones practicadas and sufridas, IVA, and the exógena working
 * papers, each with a CSV/PDF export (?format=csv|pdf and the screen's filters, ?check=1 to ask first). Built from
 * the documents; the books' figure comes from Ledger. VIEW_BOOKS. The exógena working papers are ExogenaController's.
 *
 * Periods are whole months: ?from=YYYY-MM&to=YYYY-MM (both included; one alone is a single month; none is this month
 * in Colombia), at most 12 months. 400 `invalid_date` for a month that is not YYYY-MM, `invalid_period` for an end
 * before the start or a longer range.
 */
#[Route('/api/v1/reports/taxes')]
#[IsGranted(Permission::VIEW_BOOKS)]
final class TaxReportController extends AbstractController
{
    public function __construct(
        private readonly TaxReports $reports,
        private readonly TaxTables $tables,
        private readonly ReportFiles $files,
        private readonly Calendar $calendar,
    ) {
    }

    /**
     * Retenciones practicadas (facturas de compra) or sufridas (facturas de venta), by month then kind, per tercero and
     * tax, with each total's *Según libros*. ?kind=retefuente|reteiva|reteica (default all; 400 `invalid_kind`).
     */
    #[Route('/withholdings/{side}', methods: ['GET'], requirements: ['side' => 'practiced|suffered'])]
    #[ApiResponse(WithholdingReportOutput::class)]
    public function withholdings(string $side, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $report = $this->reports->withholdings($user->companyId(), WithholdingSide::from($side), $this->range($request), self::kind($request));

        return $this->json(WithholdingReportOutput::of($report));
    }

    /** The same report as a file, with its subtotals, books' figures and differences in line. */
    #[Route('/withholdings/{side}/export', methods: ['GET'], requirements: ['side' => 'practiced|suffered'])]
    public function exportWithholdings(string $side, Request $request, #[CurrentUser] SignedInUser $user): Response
    {
        $format = ReportFiles::format($request);
        try {
            $table = $this->tables->withholdings($user->companyId(), WithholdingSide::from($side), $this->range($request), self::kind($request), ExportLimits::forFormat($format));
        } catch (ExportTooLarge $e) {
            throw ReportFiles::tooLarge($e);
        }

        return $this->files->respond($request, $user->companyId(), $table, $format);
    }

    /**
     * IVA generado and descontable by rate, ReteIVA practicada and sufrida, impoconsumo and the saldo; a bimester or
     * cuatrimestre is a range.
     */
    #[Route('/vat', methods: ['GET'])]
    #[ApiResponse(VatReportOutput::class)]
    public function vat(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->json(VatReportOutput::of($this->reports->vat($user->companyId(), $this->range($request))));
    }

    #[Route('/vat/export', methods: ['GET'])]
    public function exportVat(Request $request, #[CurrentUser] SignedInUser $user): Response
    {
        $format = ReportFiles::format($request);
        try {
            $table = $this->tables->vat($user->companyId(), $this->range($request), ExportLimits::forFormat($format));
        } catch (ExportTooLarge $e) {
            throw ReportFiles::tooLarge($e);
        }

        return $this->files->respond($request, $user->companyId(), $table, $format);
    }

    private function range(Request $request): MonthRange
    {
        $month = static function (string $name) use ($request): ?string {
            $value = $request->query->getString($name);
            if ('' === $value) {
                return null;
            }
            if (1 !== preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $value)) {
                throw ApiException::badRequest('invalid_date', \sprintf('"%s" must be a month as YYYY-MM.', $name));
            }

            return $value;
        };
        $from = $month('from');
        $to = $month('to');

        try {
            return MonthRange::of($from, $to, $this->calendar->today());
        } catch (\InvalidArgumentException $e) {
            throw ApiException::badRequest('invalid_period', $e->getMessage());
        }
    }

    private static function kind(Request $request): ?WithholdingKind
    {
        $kind = $request->query->getString('kind');
        if ('' === $kind) {
            return null;
        }

        return WithholdingKind::tryFrom($kind) ?? throw ApiException::badRequest('invalid_kind', '"kind" must be retefuente, reteiva or reteica.');
    }
}
