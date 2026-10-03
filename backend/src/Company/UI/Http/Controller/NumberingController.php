<?php

namespace App\Company\UI\Http\Controller;

use App\Company\Application\Numbering\ReviseNumberingSeries;
use App\Company\Application\Query\NumberingSeriesList;
use App\Company\UI\Http\Input\NumberingSeriesInput;
use App\Company\UI\Http\Output\NumberingSeriesOutput;
use App\Shared\Application\Command\CommandBus;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\InputMapper;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

/**
 * Numeración interna (§4.1): the prefix and next number of the cotización, recibo de caja, factura de compra, recibo de
 * pago and internal invoice series. The journal's own numbering is not here.
 */
#[Route('/api/v1/company/numbering')]
final class NumberingController extends AbstractController
{
    use EditsCompany;

    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly NumberingSeriesList $series,
    ) {
    }

    /**
     * The editable series with their prefix and next number (every role).
     */
    #[Route('', methods: ['GET'])]
    #[ApiResponse(NumberingSeriesOutput::class, key: 'items', list: true)]
    public function list(#[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->json(['items' => array_map(NumberingSeriesOutput::of(...), $this->series->list($user->companyId()))]);
    }

    /**
     * Changes a series' prefix and next number (owner). The next number never goes below the current one. 404 for the
     * journal's series or an unknown kind.
     */
    #[Route('/{kind}', methods: ['PUT'])]
    #[ApiResponse(NumberingSeriesOutput::class)]
    public function update(string $kind, Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireOwner($user);
        $input = $this->inputs->map($this->inputs->json($request), NumberingSeriesInput::class);
        $this->commands->dispatch(new ReviseNumberingSeries($user->companyId(), $user->userId(), $kind, $input->prefix, $input->nextNumber));

        return $this->json(NumberingSeriesOutput::of($this->series->get($user->companyId(), $kind)));
    }
}
