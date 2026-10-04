<?php

namespace App\Reporting\UI\Http\Controller;

use App\Reporting\Application\Dashboard\DashboardService;
use App\Reporting\UI\Http\Output\DashboardOutput;
use App\Shared\UI\Http\ApiResponse;
use App\Shared\UI\Http\Security\Permission;
use App\Shared\UI\Http\Security\SignedInUser;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/** The home screen's numbers in one request (§4.13). Books figures (cash and banks) only for who may VIEW_BOOKS. */
#[Route('/api/v1/dashboard')]
#[IsGranted(Permission::READ_DOCUMENTS)]
final class DashboardController extends AbstractController
{
    public function __construct(private readonly DashboardService $dashboard)
    {
    }

    #[Route('', methods: ['GET'])]
    #[ApiResponse(DashboardOutput::class)]
    public function show(#[CurrentUser] SignedInUser $user): JsonResponse
    {
        return $this->json(DashboardOutput::of($this->dashboard->figures($user->companyId(), $this->isGranted(Permission::VIEW_BOOKS))));
    }
}
