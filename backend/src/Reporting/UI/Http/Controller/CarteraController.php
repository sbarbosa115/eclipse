<?php

namespace App\Reporting\UI\Http\Controller;

use App\Reporting\Application\Cartera\CarteraQueries;
use App\Reporting\Application\Cartera\CarteraSide;
use App\Reporting\Application\ReportingCalendar;
use App\Reporting\UI\Http\Output\CarteraDocumentOutput;
use App\Reporting\UI\Http\Output\CarteraDocumentsOutput;
use App\Reporting\UI\Http\Output\CarteraOutput;
use App\Reporting\UI\Http\Output\CarteraRowOutput;
use App\Reporting\UI\Http\Output\CarteraTotalsOutput;
use App\Shared\Domain\Money\Money;
use App\Shared\UI\Http\ApiException;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\Security\Permission;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Uid\Uuid;

/**
 * Cartera de clientes (`clients`) and de proveedores (`suppliers`) with ageing (§4.13), for everyone who reads
 * documents. As of ?as_of= (YYYY-MM-DD, default today in Colombia).
 */
#[Route('/api/v1/reports/cartera/{side}', requirements: ['side' => 'clients|suppliers'])]
#[IsGranted(Permission::READ_DOCUMENTS)]
final class CarteraController extends AbstractController
{
    use ReadsDates;

    public function __construct(
        private readonly CarteraQueries $cartera,
        private readonly ReportingCalendar $calendar,
    ) {
    }

    /**
     * Open balances by tercero with al día, 1-30, 31-60, 61-90 and más de 90 días vencida (by due date), largest
     * first: ?q= (part of the name or the identification, matched literally), ?page, ?per_page ≤ 100 (default 25).
     * `totals` is the grand total of every tercero matching the search.
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(CarteraOutput::class)]
    public function summary(string $side, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $side = CarteraSide::from($side);
        $asOf = self::date($request, 'as_of') ?? $this->calendar->today();
        $q = $request->query->getString('q') ?: null;
        $page = max(1, $request->query->getInt('page', 1));
        $perPage = min(100, max(1, $request->query->getInt('per_page', 25)));

        $totals = $this->cartera->totals($user->companyId(), $side, $asOf, $q);
        $rows = $this->cartera->summary($user->companyId(), $side, $asOf, $q, $perPage, ($page - 1) * $perPage);

        return $this->json(new CarteraOutput(
            $asOf->format('Y-m-d'),
            array_map(CarteraRowOutput::of(...), $rows),
            $totals->terceros,
            $page,
            $perPage,
            CarteraTotalsOutput::of($totals),
        ));
    }

    /** One tercero's open documents, the soonest due first. 404 for a tercero without a balance. */
    #[Route('/{terceroId}', requirements: ['terceroId' => '[0-9a-fA-F-]{36}'], methods: ['GET'])]
    #[ApiResponse(CarteraDocumentsOutput::class)]
    public function documents(string $side, string $terceroId, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        if (!Uuid::isValid($terceroId)) {
            throw ApiException::notFound();
        }
        $side = CarteraSide::from($side);
        $asOf = self::date($request, 'as_of') ?? $this->calendar->today();
        $documents = $this->cartera->documents($user->companyId(), $side, $asOf, Uuid::fromString($terceroId));
        if ([] === $documents) {
            throw ApiException::notFound();
        }

        return $this->json(new CarteraDocumentsOutput(
            $asOf->format('Y-m-d'),
            $terceroId,
            $documents[0]->terceroName,
            array_map(CarteraDocumentOutput::of(...), $documents),
            Money::sum(...array_map(static fn ($d) => Money::of($d->balance), $documents))->toString(),
        ));
    }
}
