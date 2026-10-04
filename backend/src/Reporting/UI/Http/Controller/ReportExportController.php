<?php

namespace App\Reporting\UI\Http\Controller;

use App\Ledger\Application\Query\JournalFilter;
use App\Reporting\Application\Cartera\CarteraSide;
use App\Reporting\Application\Export\CarteraTables;
use App\Reporting\Application\Export\CsvEncoder;
use App\Reporting\Application\Export\ExportFormat;
use App\Reporting\Application\Export\ExportLimits;
use App\Reporting\Application\Export\ExportTooLarge;
use App\Reporting\Application\Export\LedgerTables;
use App\Reporting\Application\Export\ReportPdf;
use App\Reporting\Application\Export\TabularReport;
use App\Shared\Domain\Calendar;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\Security\Permission;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Every report as a file (§4.13, §9 Q26): ?format=csv (UTF-8 with BOM, `;` separated, decimal points; streamed) or
 * ?format=pdf (the company's frame); ?check=1 only rehearses it (204, or the 422 below). Cartera is for who reads documents; the libros for who may view the books. A
 * report above the row cap (ExportLimits) is refused with 422 `export_too_large`, never cut short: narrow the filters.
 */
#[Route('/api/v1/reports')]
final class ReportExportController extends AbstractController
{
    use ReadsDates;

    private const LEDGER_REPORTS = 'journal|trial-balance|income-statement|balance-sheet';

    public function __construct(
        private readonly CarteraTables $cartera,
        private readonly LedgerTables $ledger,
        private readonly ReportPdf $pdf,
        private readonly Calendar $calendar,
    ) {
    }

    /**
     * Cartera by tercero (default) or, with ?detail=1, document by document. ?as_of=, ?q= as the screen.
     */
    #[Route('/cartera/{side}/export', requirements: ['side' => 'clients|suppliers'], methods: ['GET'])]
    #[IsGranted(Permission::READ_DOCUMENTS)]
    public function cartera(string $side, Request $request, #[CurrentUser] SignedInUser $user): Response
    {
        $format = self::format($request);
        $limit = ExportLimits::forFormat($format);
        $asOf = self::date($request, 'as_of') ?? $this->calendar->today();
        $q = $request->query->getString('q') ?: null;
        $side = CarteraSide::from($side);

        try {
            $report = $request->query->getBoolean('detail')
                ? $this->cartera->documents($user->companyId(), $side, $asOf, $q, $limit)
                : $this->cartera->byTercero($user->companyId(), $side, $asOf, $q, $limit);
        } catch (ExportTooLarge $e) {
            throw self::tooLarge($e);
        }

        return $this->respond($request, $user, $report, $format);
    }

    /**
     * The ledger's books: journal (?from, ?to, ?account, ?tercero_id), trial-balance and income-statement (?from, ?to,
     * default the year so far), balance-sheet (?date, default today).
     */
    #[Route('/ledger/{report}/export', requirements: ['report' => self::LEDGER_REPORTS], methods: ['GET'])]
    #[IsGranted(Permission::VIEW_BOOKS)]
    public function ledger(string $report, Request $request, #[CurrentUser] SignedInUser $user): Response
    {
        $format = self::format($request);
        $limit = ExportLimits::forFormat($format);
        $company = $user->companyId();
        $today = $this->calendar->today();
        $from = self::date($request, 'from');
        $to = self::date($request, 'to');
        $yearStart = $today->setDate((int) $today->format('Y'), 1, 1);

        try {
            $table = match ($report) {
                'journal' => $this->ledger->journal($company, new JournalFilter(
                    $from,
                    $to,
                    1 === preg_match('/^\d{1,16}$/', $request->query->getString('account')) ? $request->query->getString('account') : null,
                    Uuid::isValid($request->query->getString('tercero_id')) ? Uuid::fromString($request->query->getString('tercero_id')) : null,
                ), $limit),
                'trial-balance' => $this->ledger->trialBalance($company, $from ?? $yearStart, $to ?? $today, $limit),
                'income-statement' => $this->ledger->incomeStatement($company, $from ?? $yearStart, $to ?? $today, $limit),
                default => $this->ledger->balanceSheet($company, self::date($request, 'date') ?? $today, $limit),
            };
        } catch (ExportTooLarge $e) {
            throw self::tooLarge($e);
        }

        return $this->respond($request, $user, $table, $format);
    }

    private function respond(Request $request, SignedInUser $user, TabularReport $report, ExportFormat $format): Response
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
                $bytes = $this->pdf->render($user->companyId(), $report);
            } catch (ExportTooLarge $e) {
                throw self::tooLarge($e);
            }

            return new Response($bytes, 200, [
                'Content-Type' => 'application/pdf',
                'Content-Disposition' => HeaderUtils::makeDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, $report->fileName.'.pdf'),
                'Cache-Control' => 'private, no-store',
            ]);
        }

        $response = new StreamedResponse(static function () use ($report): void {
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

        return $response;
    }

    private static function format(Request $request): ExportFormat
    {
        return ExportFormat::tryFrom($request->query->getString('format', 'csv')) ?? throw ApiException::badRequest('invalid_format', '"format" must be csv or pdf.');
    }

    public static function tooLarge(ExportTooLarge $e): ApiException
    {
        return new ApiException(422, 'export_too_large', \sprintf('The report has about %d rows; one export holds at most %d. Narrow the dates or filters.', $e->rows, $e->limit));
    }
}
