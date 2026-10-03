<?php

namespace App\Company\UI\Http\Controller;

use App\Company\Application\Profile\ConfirmManualInvoicing;
use App\Company\Application\Query\Resolutions;
use App\Company\Application\Resolution\SaveResolution;
use App\Company\Application\Resolution\UpdateResolutionWarnings;
use App\Company\UI\Http\Input\ResolutionInput;
use App\Company\UI\Http\Input\ResolutionWarningsInput;
use App\Company\UI\Http\Output\ResolutionSettingsOutput;
use App\Company\UI\Http\Output\ResolutionStatusOutput;
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
 * The company's invoicing resolution (§4.1): one per company. Every role reads it and its status; the owner sets it up.
 */
#[Route('/api/v1/company')]
final class ResolutionController extends AbstractController
{
    use EditsCompany;

    public function __construct(
        private readonly InputMapper $inputs,
        private readonly CommandBus $commands,
        private readonly Resolutions $resolutions,
    ) {
    }

    /**
     * The resolution (null before it exists), its status today and whether manual invoicing was confirmed.
     */
    #[Route('/resolution', methods: ['GET'])]
    #[ApiResponse(ResolutionSettingsOutput::class)]
    public function show(#[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->json(ResolutionSettingsOutput::of($this->resolutions->settings($user->companyId())));
    }

    /**
     * Where the resolution stands today: missing, not_yet_valid, active, expired or exhausted; the numbers and days
     * left; `warning` when active but under the company's thresholds.
     */
    #[Route('/resolution/status', methods: ['GET'])]
    #[ApiResponse(ResolutionStatusOutput::class)]
    public function status(#[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->json(ResolutionStatusOutput::of($this->resolutions->status($user->companyId())));
    }

    /**
     * Sets the resolution up (owner). 409 `resolution_exists` when there is one. Modalidad manual is refused until the
     * owner confirmed the DIAN permission.
     */
    #[Route('/resolution', methods: ['POST'])]
    #[ApiResponse(ResolutionSettingsOutput::class, status: 201)]
    public function create(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->save($request, $user, true, 201);
    }

    /**
     * Edits the resolution (owner). Once invoices were numbered from it, desde and the prefix cannot change and hasta
     * cannot go below the last number used.
     */
    #[Route('/resolution', methods: ['PUT'])]
    #[ApiResponse(ResolutionSettingsOutput::class)]
    public function update(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->save($request, $user, false, 200);
    }

    /**
     * Chooses when to be warned: fewer numbers or fewer days left than these (owner).
     */
    #[Route('/resolution/warnings', methods: ['PUT'])]
    #[ApiResponse(ResolutionSettingsOutput::class)]
    public function warnings(Request $request, #[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireOwner($user);
        $input = $this->inputs->map($this->inputs->json($request), ResolutionWarningsInput::class);
        $this->commands->dispatch(new UpdateResolutionWarnings($user->companyId(), $user->userId(), $input->warningNumbers, $input->warningDays));

        return $this->json(ResolutionSettingsOutput::of($this->resolutions->settings($user->companyId())));
    }

    /**
     * The owner confirms the company holds the DIAN permission to invoice manually; logged. Only then can a
     * resolution be modalidad manual.
     */
    #[Route('/manual-invoicing-confirmation', methods: ['POST'])]
    #[ApiResponse(ResolutionSettingsOutput::class)]
    public function confirmManualInvoicing(#[CurrentUser] SignedInUser $user): JsonResponse
    {
        $this->requireOwner($user);
        $this->commands->dispatch(new ConfirmManualInvoicing($user->companyId(), $user->userId()));

        return $this->json(ResolutionSettingsOutput::of($this->resolutions->settings($user->companyId())));
    }

    private function save(Request $request, SignedInUser $user, bool $create, int $status): JsonResponse
    {
        $this->requireOwner($user);
        $input = $this->inputs->map($this->inputs->json($request), ResolutionInput::class);
        $this->commands->dispatch(new SaveResolution($user->companyId(), $user->userId(), $create, $input->resolutionNumber, $input->prefix, $input->rangeFrom, $input->rangeTo, $input->validFrom, $input->validTo, $input->mode));

        return $this->json(ResolutionSettingsOutput::of($this->resolutions->settings($user->companyId())), $status);
    }
}
