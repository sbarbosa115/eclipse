<?php

namespace App\Reporting\UI\Http\Controller;

use App\Reporting\UI\Http\Output\ExogenaOutput;
use App\Reporting\UI\Http\Output\VatReportOutput;
use App\Reporting\UI\Http\Output\WithholdingReportOutput;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Reportes › Impuestos (observations PRD §4.7): retenciones practicadas and sufridas, IVA, and the exógena working
 * papers, each with a CSV/PDF export (?format=csv|pdf and the screen's filters, ?check=1 to ask first). Built from
 * the documents; the books' figure comes from Ledger. VIEW_BOOKS. Contract only: the "tax-reports" item builds the
 * retenciones and IVA routes, the "exogena" item the exógena ones.
 */
#[Route('/api/v1/reports/taxes')]
#[IsGranted(Permission::VIEW_BOOKS)]
final class TaxReportController extends AbstractController
{
    /** ?from=YYYY-MM&to=YYYY-MM (default this month), ?kind=retefuente|reteiva|reteica. 400 invalid_date. */
    #[Route('/withholdings/{side}', methods: ['GET'], requirements: ['side' => 'practiced|suffered'])]
    #[ApiResponse(WithholdingReportOutput::class)]
    public function withholdings(string $side): JsonResponse
    {
        throw ApiException::notImplemented('tax-reports');
    }

    #[Route('/withholdings/{side}/export', methods: ['GET'], requirements: ['side' => 'practiced|suffered'])]
    public function exportWithholdings(string $side): Response
    {
        throw ApiException::notImplemented('tax-reports');
    }

    /** ?from=YYYY-MM&to=YYYY-MM: a bimester or cuatrimestre is a range. */
    #[Route('/vat', methods: ['GET'])]
    #[ApiResponse(VatReportOutput::class)]
    public function vat(): JsonResponse
    {
        throw ApiException::notImplemented('tax-reports');
    }

    #[Route('/vat/export', methods: ['GET'])]
    public function exportVat(): Response
    {
        throw ApiException::notImplemented('tax-reports');
    }

    /** ?year= (default last year), ?threshold= pesos (default 0: everyone). */
    #[Route('/exogena/{format}', methods: ['GET'], requirements: ['format' => '1001|1003|1005|1006|1007|1008|1009'])]
    #[ApiResponse(ExogenaOutput::class)]
    public function exogena(string $format): JsonResponse
    {
        throw ApiException::notImplemented('exogena');
    }

    /** 422 exogena_terceros_incomplete while a tercero of the format has no municipality. */
    #[Route('/exogena/{format}/export', methods: ['GET'], requirements: ['format' => '1001|1003|1005|1006|1007|1008|1009'])]
    public function exportExogena(string $format): Response
    {
        throw ApiException::notImplemented('exogena');
    }
}
